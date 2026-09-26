<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

/**
 * Thrown when a resolver returns false from authorize().
 */
class AuthorizationException extends \RuntimeException implements ClientAware, ProvidesExtensions
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? trans('laragraph::errors.authorization.default'));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function getExtensions(): ?array
    {
        return ['code' => 'UNAUTHORIZED', 'category' => 'authorization'];
    }
}
