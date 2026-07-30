<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Controllers;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
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

        return $this->laragraph->execute(
            query: $query,
            context: $request,
            variables: $variables,
            operationName: $operationName,
            schemaName: $schemaName,
        );
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

        if (is_string($body) && trim($body) !== '') {
            return ['query' => $body];
        }

        return is_array($body) ? $body : [];
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