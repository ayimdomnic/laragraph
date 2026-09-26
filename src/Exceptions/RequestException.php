<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * A GraphQL request rejected before execution (wrong HTTP method, unknown
 * persisted query, …). Rendered by the controller as a spec-shaped
 * `{"errors": [...]}` body with a machine-readable `extensions.code`.
 *
 * Not `final`: {@see BatchingDisabledException} and {@see BatchLimitExceededException}
 * extend it so every pre-execution error shares the same response shape.
 */
class RequestException extends \RuntimeException
{
    /**
     * @param int|null              $status  HTTP status; null lets the controller pick one
     *                                       from the negotiated response media type.
     * @param array<string, string> $headers Extra response headers (e.g. `Allow`).
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $status = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self($message, 'BAD_REQUEST', 400);
    }

    public static function schemaNotFound(string $schemaName): self
    {
        return new self(
            trans('laragraph::errors.request.schema_not_found', ['schema' => $schemaName]),
            'SCHEMA_NOT_FOUND',
            404,
        );
    }

    public static function methodNotAllowed(): self
    {
        return new self(
            trans('laragraph::errors.request.method_not_allowed'),
            'METHOD_NOT_ALLOWED',
            405,
            ['Allow' => 'POST'],
        );
    }

    public static function persistedQueryNotFound(): self
    {
        // Message and code follow the Apollo APQ protocol so clients retry with the full query.
        return new self(trans('laragraph::errors.request.persisted_query_not_found'), 'PERSISTED_QUERY_NOT_FOUND');
    }

    public static function persistedQueryHashMismatch(): self
    {
        return new self(trans('laragraph::errors.request.persisted_query_hash_mismatch'), 'PERSISTED_QUERY_HASH_MISMATCH', 400);
    }

    public static function persistedQueryRequired(): self
    {
        return new self(trans('laragraph::errors.request.persisted_query_required'), 'PERSISTED_QUERY_REQUIRED', 400);
    }

    /**
     * @return array{errors: list<array{message: string, extensions: array{code: string}}>}
     */
    public function toResponse(): array
    {
        return ['errors' => [[
            'message'    => $this->getMessage(),
            'extensions' => ['code' => $this->errorCode],
        ]]];
    }
}
