<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http;

use Ayimdomnic\Laragraph\Contracts\ResponseNegotiatorInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serialises a result, negotiating the GraphQL-over-HTTP response media type.
 *
 * Clients that send `Accept: application/graphql-response+json` receive that
 * media type and a 4xx status whenever the request fails before execution;
 * others get `application/json` with the traditional always-200 behaviour.
 */
final class ResponseNegotiator implements ResponseNegotiatorInterface
{
    /** The GraphQL-over-HTTP response media type. */
    public const GRAPHQL_RESPONSE_MEDIA_TYPE = 'application/graphql-response+json';

    /**
     * @param array<mixed> $result
     * @param array<string, string> $headers
     */
    public function negotiate(Request $request, array $result, ?int $status = null, array $headers = []): JsonResponse
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
}
