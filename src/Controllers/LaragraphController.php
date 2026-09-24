<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Controllers;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionManager;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRegistrar;
use Ayimdomnic\Laragraph\Support\Operation;
use Ayimdomnic\Laragraph\Support\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
class LaragraphController extends BaseController
{
    /** The GraphQL-over-HTTP response media type. */
    public const GRAPHQL_RESPONSE_MEDIA_TYPE = 'application/graphql-response+json';

    public function __construct(protected readonly Laragraph $laragraph) {}

    /**
     * Execute a GraphQL query / mutation.
     *
     * Follows the GraphQL-over-HTTP specification: clients that send
     * `Accept: application/graphql-response+json` receive that media type and
     * a 4xx status whenever the request fails before execution; others get
     * `application/json` with the traditional always-200 behaviour.
     */
    public function query(Request $request, string $schemaName = 'default'): JsonResponse
    {
        $parsed = $this->parseRequest($request);

        // Support batched queries (array of query objects)
        if (isset($parsed[0]) && is_array($parsed[0])) {
            try {
                $results = $this->laragraph->executeBatch($parsed, $request, $schemaName);
            } catch (BatchingDisabledException|BatchLimitExceededException $e) {
                return $this->respond($request, ['errors' => [['message' => $e->getMessage()]]], 400);
            }

            return $this->respond($request, $results, 200);
        }

        try {
            $result = $this->executeOne($parsed, $request, $schemaName);
        } catch (RequestException $e) {
            return $this->respond($request, $e->toResponse(), $e->status, $e->headers);
        }

        return $this->respond($request, $result);
    }

    /**
     * Serve the GraphiQL browser IDE.
     */
    public function graphiql(Request $request, string $schemaName = 'default'): Response
    {
        $endpoint = url(config('laragraph.route.prefix', 'graphql') . '/' . ($schemaName !== 'default' ? $schemaName : ''));

        return response()
            ->view('laragraph::graphiql', [
                'endpoint' => rtrim($endpoint, '/'),
                'title'    => config('laragraph.graphiql.title', 'Laragraph — GraphiQL'),
            ]);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws RequestException When the request is rejected before execution.
     */
    protected function executeOne(array $data, Request $request, string $schemaName): array
    {
        $query         = (string) ($data['query'] ?? '');
        $variables     = $this->castVariables($data['variables'] ?? null);
        $operationName = isset($data['operationName']) ? (string) $data['operationName'] : null;

        if (config('laragraph.persisted_queries.enabled', false)) {
            $query = $this->resolvePersistedQuery($query, $data);
        }

        // GraphQL-over-HTTP: GET must never execute a mutation (it would be CSRF-able).
        if ($request->isMethod('GET') && Operation::isMutation($query, $operationName)) {
            throw RequestException::methodNotAllowed();
        }

        if ($query !== '' && $this->isSubscriptionOperation($query, $operationName)) {
            return $this->registerSubscription($query, $variables, $operationName, $schemaName, $request);
        }

        return $this->laragraph->execute(
            query: $query,
            context: $request,
            variables: $variables,
            operationName: $operationName,
            schemaName: $schemaName,
        );
    }

    /**
     * Resolve the query text for a request when persisted queries are enabled.
     *
     * - `queryId` or the APQ `extensions.persistedQuery.sha256Hash` alone
     *   looks the query up in the store.
     * - APQ registration: a full query *plus* its SHA-256 hash stores it for
     *   subsequent hash-only requests (unless `persisted_queries.only` is on).
     * - `persisted_queries.only` turns the store into an allow-list: query
     *   text is executed only when it is already stored under its hash.
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    protected function resolvePersistedQuery(string $query, array $data): string
    {
        /** @var PersistedQueryStoreInterface $store */
        $store   = app(PersistedQueryStoreInterface::class);
        $only    = (bool) config('laragraph.persisted_queries.only', false);
        $queryId = $data['queryId'] ?? $data['extensions']['persistedQuery']['sha256Hash'] ?? null;
        $queryId = is_scalar($queryId) ? (string) $queryId : null;

        if ($query === '') {
            if ($queryId === null) {
                return $query;
            }

            return $store->get($queryId) ?? throw RequestException::persistedQueryNotFound();
        }

        $hash = hash('sha256', $query);

        if ($only) {
            return $store->has($hash) ? $query : throw RequestException::persistedQueryRequired();
        }

        if ($queryId !== null && isset($data['extensions']['persistedQuery'])) {
            if (!hash_equals($hash, strtolower($queryId))) {
                throw RequestException::persistedQueryHashMismatch();
            }

            if (config('laragraph.persisted_queries.apq', true)) {
                $store->set($hash, $query);
            }
        }

        return $query;
    }

    /**
     * Whether $query's (matching) operation is a `subscription`.
     *
     * webonyx/graphql-php has no dedicated subscription-execution entrypoint
     * — Laragraph must recognise the operation type itself, before deciding
     * whether to execute normally or register a new subscriber.
     */
    protected function isSubscriptionOperation(string $query, ?string $operationName): bool
    {
        return Operation::isSubscription($query, $operationName);
    }

    /**
     * Serialise a result, negotiating the GraphQL-over-HTTP response media type.
     *
     * @param array<mixed>          $result
     * @param array<string, string> $headers
     */
    protected function respond(Request $request, array $result, ?int $status = null, array $headers = []): JsonResponse
    {
        $graphqlResponse = str_contains(
            strtolower((string) $request->header('Accept', '')),
            self::GRAPHQL_RESPONSE_MEDIA_TYPE,
        );

        if ($graphqlResponse) {
            $headers['Content-Type'] = self::GRAPHQL_RESPONSE_MEDIA_TYPE . '; charset=utf-8';

            // No `data` entry means the request failed before execution began.
            $status ??= array_key_exists('data', $result) || array_is_list($result) ? 200 : 400;
        }

        return response()->json($result, $status ?? 200, $headers);
    }

    /**
     * Register a new subscriber instead of executing normally.
     *
     * Runs the query through the normal auth/validation/middleware pipeline
     * (via Laragraph::execute(), with `subscribing` flagged on the context)
     * so a subscription request is authorized exactly like any other field,
     * but the field's `handleField()` calls `subscribe()` rather than
     * `resolve()` — see {@see Subscription}.
     *
     * @return array<string, mixed>
     * @param array<string, mixed> $variables
     */
    protected function registerSubscription(
        string $query,
        array $variables,
        ?string $operationName,
        string $schemaName,
        Request $request,
    ): array {
        if (!config('laragraph.subscriptions.enabled', false)) {
            return ['errors' => [[
                'message' => 'Subscriptions are disabled. Enable via the laragraph.subscriptions.enabled config.',
            ]]];
        }

        $registrar = new SubscriptionRegistrar();

        $context                        = GraphQLContext::fromRequest($request);
        $context->subscribing           = true;
        $context->subscriptionRegistrar = $registrar;

        $result = $this->laragraph->execute($query, $context, $variables, $operationName, $schemaName);

        if (!empty($result['errors'])) {
            return $result;
        }

        $channel = $registrar->channel();

        if ($channel === null) {
            return ['errors' => [[
                'message' => 'The subscription field did not resolve a channel via subscribe().',
            ]]];
        }

        $subscriberId = app(SubscriptionManager::class)->register($channel, [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operationName,
            'schemaName'    => $schemaName,
        ]);

        return [
            'data'       => $result['data'] ?? null,
            'extensions' => array_merge($result['extensions'] ?? [], [
                'subscription' => [
                    'channel'      => $channel,
                    'subscriberId' => $subscriberId,
                ],
            ]),
        ];
    }

    /**
     * Parse a GraphQL HTTP request following the GraphQL-over-HTTP spec.
     *
     * Supports:
     *  - GET  ?query=...&variables=...&operationName=...
     *  - POST application/json
     *  - POST application/x-www-form-urlencoded
     *  - POST multipart/form-data  (file uploads via the multipart spec)
     *
     * @return array<array-key, mixed> A single operation, or a list of operations for a batch.
     */
    protected function parseRequest(Request $request): array
    {
        if ($request->isMethod('GET')) {
            return [
                'query'         => $request->query('query', ''),
                'variables'     => $request->query('variables'),
                'operationName' => $request->query('operationName'),
                'queryId'       => $request->query('queryId'),
                'extensions'    => $this->castVariables($request->query('extensions')),
            ];
        }

        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->parseMultipartRequest($request);
        }

        if (str_contains($contentType, 'application/graphql')) {
            return ['query' => (string) $request->getContent()];
        }

        // application/json or form-urlencoded
        $body = $request->json()->all();

        if (empty($body)) {
            return $request->all();
        }

        return $body;
    }

    /**
     * Parse a multipart/form-data request according to the GraphQL multipart
     * request spec (https://github.com/jaydenseric/graphql-multipart-request-spec).
     *
     * @return array<array-key, mixed>
     */
    protected function parseMultipartRequest(Request $request): array
    {
        $operationsJson = $request->input('operations', '{}');
        $mapJson        = $request->input('map', '{}');

        $operations = json_decode($operationsJson, true) ?? [];
        $map        = json_decode($mapJson, true) ?? [];

        // Attach uploaded files to the variables using the map
        foreach ($map as $fileKey => $paths) {
            $file = $request->file((string) $fileKey);
            foreach ((array) $paths as $path) {
                data_set($operations, $path, $file);
            }
        }

        return $operations;
    }

    /**
     * @return array<string, mixed>
     */
    protected function castVariables(mixed $variables): array
    {
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($variables) ? $variables : [];
    }
}
