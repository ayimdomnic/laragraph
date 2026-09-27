<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Http\ResponseNegotiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serialises a GraphQL result, negotiating the GraphQL-over-HTTP response media type.
 *
 * @see ResponseNegotiator the built-in implementation.
 */
interface ResponseNegotiatorInterface
{
    /**
     * @param array<mixed> $result
     * @param array<string, string> $headers
     */
    public function negotiate(Request $request, array $result, ?int $status = null, array $headers = []): JsonResponse;
}
