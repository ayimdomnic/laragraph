<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

use GraphQL\Error\ClientAware;

/**
 * Thrown when a resolver returns false from authorize().
 */
class AuthorizationException extends \RuntimeException implements ClientAware
{
    public function __construct(string $message = 'Unauthorized.')
    {
        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return true;
    }
}
