<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * A GraphQL request rejected before execution (wrong HTTP method, unknown
 * persisted query, …). Rendered by the controller as a spec-shaped
 * `{"errors": [...]}` body with a machine-readable `extensions.code`.
 */
final class RequestException extends \RuntimeException
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

    public static function methodNotAllowed(): self
    {
        return new self(
            'Mutations cannot be executed over GET; send them with POST.',
            'METHOD_NOT_ALLOWED',
            405,
            ['Allow' => 'POST'],
        );
    }

    public static function persistedQueryNotFound(): self
    {
        // Message and code follow the Apollo APQ protocol so clients retry with the full query.
        return new self('PersistedQueryNotFound', 'PERSISTED_QUERY_NOT_FOUND');
    }

    public static function persistedQueryHashMismatch(): self
    {
        return new self('provided sha does not match query', 'PERSISTED_QUERY_HASH_MISMATCH', 400);
    }

    public static function persistedQueryRequired(): self
    {
        return new self('Only persisted queries are allowed.', 'PERSISTED_QUERY_REQUIRED', 400);
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
