<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\Http\RequestParser;
use Illuminate\Http\Request;

/**
 * Parses a GraphQL-over-HTTP request (GET, JSON, form-urlencoded, or
 * multipart) into an operation array (or a list of them, for a batch).
 *
 * @see RequestParser the built-in implementation.
 */
interface RequestParserInterface
{
    /**
     * @return array<array-key, mixed> A single operation, or a list of operations for a batch.
     *
     * @throws RequestException When the body cannot be parsed.
     */
    public function parse(Request $request): array;

    /**
     * Reject request parameters of the wrong shape (GraphQL over HTTP §6.1).
     *
     * @phpstan-assert array<string, mixed> $operation
     *
     * @throws RequestException
     */
    public function assertOperation(mixed $operation): void;

    /**
     * @return array<string, mixed>
     */
    public function castVariables(mixed $variables): array;
}
