<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http;

use Ayimdomnic\Laragraph\Contracts\RequestParserInterface;
use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Illuminate\Http\Request;

/**
 * Parses a GraphQL-over-HTTP request following the GraphQL-over-HTTP spec.
 *
 * Supports:
 *  - GET  ?query=...&variables=...&operationName=...
 *  - POST application/json
 *  - POST application/x-www-form-urlencoded
 *  - POST multipart/form-data  (file uploads via the multipart spec)
 */
final class RequestParser implements RequestParserInterface
{
    /**
     * @return array<array-key, mixed> A single operation, or a list of operations for a batch.
     *
     * @throws RequestException When the body cannot be parsed.
     */
    public function parse(Request $request): array
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
            return $this->parseMultipart($request);
        }

        if (str_contains($contentType, 'application/graphql')) {
            return ['query' => (string) $request->getContent()];
        }

        if ($request->isJson() && trim((string) $request->getContent()) !== '') {
            $body = json_decode((string) $request->getContent(), true);

            if (!is_array($body)) {
                throw RequestException::badRequest('The request body must be a JSON object or a list of them.');
            }

            return $body === [] ? $request->all() : $body;
        }

        // application/x-www-form-urlencoded (or an empty body)
        return $request->all();
    }

    /**
     * Reject request parameters of the wrong shape (GraphQL over HTTP §6.1)
     * with a 400 instead of letting them fail deep inside execution.
     *
     * @phpstan-assert array<string, mixed> $operation
     *
     * @throws RequestException
     */
    public function assertOperation(mixed $operation): void
    {
        if (!is_array($operation) || ($operation !== [] && array_is_list($operation))) {
            throw RequestException::badRequest('Each GraphQL operation must be a JSON object.');
        }

        foreach (['query', 'operationName', 'queryId'] as $key) {
            if (isset($operation[$key]) && !is_string($operation[$key])) {
                throw RequestException::badRequest("`{$key}` must be a string.");
            }
        }

        foreach (['variables', 'extensions'] as $key) {
            $value = $operation[$key] ?? null;

            if (is_string($value)) {
                $value = json_decode($value, true);

                if (!is_array($value)) {
                    throw RequestException::badRequest("`{$key}` must be a JSON object.");
                }
            }

            if ($value !== null && (!is_array($value) || ($value !== [] && array_is_list($value)))) {
                throw RequestException::badRequest("`{$key}` must be an object.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function castVariables(mixed $variables): array
    {
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($variables) ? $variables : [];
    }

    /**
     * Parse a multipart/form-data request according to the GraphQL multipart
     * request spec (https://github.com/jaydenseric/graphql-multipart-request-spec).
     *
     * @return array<array-key, mixed>
     *
     * @throws RequestException When the multipart fields are malformed.
     */
    private function parseMultipart(Request $request): array
    {
        $operations = json_decode($this->multipartField($request, 'operations'), true);
        $map        = json_decode($this->multipartField($request, 'map'), true);

        if (!is_array($operations) || !is_array($map)) {
            throw RequestException::badRequest('Multipart `operations` and `map` fields must be JSON.');
        }

        // Attach uploaded files to the variables using the map
        foreach ($map as $fileKey => $paths) {
            $file = $request->file((string) $fileKey);

            foreach ((array) $paths as $path) {
                if (!is_string($path) || !preg_match('/^(\d+\.)?variables(\.[^.*]+)+$/', $path)) {
                    throw RequestException::badRequest('Multipart `map` paths must point into `variables`.');
                }

                data_set($operations, $path, $file);
            }
        }

        return $operations;
    }

    /**
     * @throws RequestException When the field is missing or not a string.
     */
    private function multipartField(Request $request, string $name): string
    {
        $value = $request->input($name);

        if (!is_string($value)) {
            throw RequestException::badRequest("Multipart requests need a JSON `{$name}` field.");
        }

        return $value;
    }
}
