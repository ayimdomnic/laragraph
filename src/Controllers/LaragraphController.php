<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Controllers;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionManager;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRegistrar;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
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
    public function __construct(protected readonly Laragraph $laragraph) {}

    /**
     * Execute a GraphQL query / mutation.
     */
    public function query(Request $request, string $schemaName = 'default'): JsonResponse
    {
        $parsed = $this->parseRequest($request);

        // Support batched queries (array of query objects)
        if (isset($parsed[0]) && is_array($parsed[0])) {
            try {
                $results = $this->laragraph->executeBatch($parsed, $request, $schemaName);
            } catch (BatchingDisabledException|BatchLimitExceededException $e) {
                return response()->json(
                    ['errors' => [['message' => $e->getMessage()]]],
                    400,
                );
            }
            return response()->json($results);
        }

        return response()->json($this->executeOne($parsed, $request, $schemaName));
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

    protected function executeOne(array $data, Request $request, string $schemaName): array
    {
        $query         = (string) ($data['query'] ?? '');
        $variables     = $this->castVariables($data['variables'] ?? null);
        $operationName = isset($data['operationName']) ? (string) $data['operationName'] : null;

        // Persisted query resolution: swap a query ID for its full text
        if ($query === '' && config('laragraph.persisted_queries.enabled', false)) {
            $queryId = $data['queryId']
                ?? $data['extensions']['persistedQuery']['sha256Hash']
                ?? null;

            if ($queryId !== null) {
                /** @var PersistedQueryStoreInterface $store */
                $store       = app(PersistedQueryStoreInterface::class);
                $resolvedQuery = $store->get((string) $queryId);

                if ($resolvedQuery === null) {
                    return ['errors' => [['message' => 'PersistedQueryNotFound: ' . $queryId]]];
                }

                $query = $resolvedQuery;
            }
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
     * Whether $query's (matching) operation is a `subscription`.
     *
     * webonyx/graphql-php has no dedicated subscription-execution entrypoint
     * — Laragraph must recognise the operation type itself, before deciding
     * whether to execute normally or register a new subscriber.
     */
    protected function isSubscriptionOperation(string $query, ?string $operationName): bool
    {
        try {
            $document = Parser::parse($query);
        } catch (\Throwable) {
            // Let normal execution run and surface the syntax error as usual.
            return false;
        }

        foreach ($document->definitions as $definition) {
            if (!$definition instanceof OperationDefinitionNode) {
                continue;
            }

            if ($operationName !== null && $definition->name?->value !== $operationName) {
                continue;
            }

            return $definition->operation === 'subscription';
        }

        return false;
    }

    /**
     * Register a new subscriber instead of executing normally.
     *
     * Runs the query through the normal auth/validation/middleware pipeline
     * (via Laragraph::execute(), with `subscribing` flagged on the context)
     * so a subscription request is authorized exactly like any other field,
     * but the field's `handleField()` calls `subscribe()` rather than
     * `resolve()` — see {@see \Ayimdomnic\Laragraph\Support\Subscription}.
     *
     * @return array<string, mixed>
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

        $request->subscribing            = true;
        $request->subscriptionRegistrar  = $registrar;

        $result = $this->laragraph->execute($query, $request, $variables, $operationName, $schemaName);

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
     */
    protected function parseRequest(Request $request): array
    {
        if ($request->isMethod('GET')) {
            return [
                'query'         => $request->query('query', ''),
                'variables'     => $request->query('variables'),
                'operationName' => $request->query('operationName'),
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
            $body = $request->all();
        }

        return $body;
    }

    /**
     * Parse a multipart/form-data request according to the GraphQL multipart
     * request spec (https://github.com/jaydenseric/graphql-multipart-request-spec).
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

    protected function castVariables(mixed $variables): array
    {
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($variables) ? $variables : [];
    }
}