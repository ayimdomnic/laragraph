<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * A resolver-facing, localizable, coded GraphQL error.
 *
 * This is the recommended way to report a domain/business error from a
 * resolver: it is always client-safe, its message is resolved through
 * Laravel's translator (falling back to the key itself, exactly like
 * `trans()`/`__()` do, when no translation line exists), and its `$code`
 * plus any `$extra` context are exposed under `extensions` automatically —
 * {@see Laragraph::formatError()} picks them up via
 * {@see ProvidesExtensions} without any special-casing.
 *
 * Generate one with `php artisan laragraph:make:exception`.
 */
class GraphQLException extends \RuntimeException implements ClientAware, ProvidesExtensions
{
    /**
     * @param string               $key       Translation key, e.g. 'errors.invalid_credentials'.
     * @param string               $errorCode Machine-readable code, e.g. 'INVALID_CREDENTIALS'.
     * @param array<string, mixed> $replace  Substituted into the translated message (`:placeholder`).
     * @param array<string, mixed> $extra    Extra data merged into `extensions`. Always reaches the
     *                                       client — unlike debug-gated `debugMessage`/`trace` — so
     *                                       never put secrets or internal details here.
     * @param string               $category Grouped alongside `validation`/`authorization`/etc.
     * @param string|null          $message  Overrides the translated message outright.
     */
    public function __construct(
        string $key,
        public readonly string $errorCode,
        protected readonly array $replace = [],
        protected readonly array $extra = [],
        protected readonly string $category = 'application',
        ?string $message = null,
    ) {
        parent::__construct($message ?? trans($key, $replace));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function getExtensions(): ?array
    {
        return ['code' => $this->errorCode, 'category' => $this->category, ...$this->extra];
    }
}
