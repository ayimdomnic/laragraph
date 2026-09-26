<?php

declare(strict_types=1);

namespace App\GraphQL\Exceptions;

use Ayimdomnic\Laragraph\Exceptions\GraphQLException;

/**
 * Thrown by LoginMutation when the given credentials don't match a user.
 *
 * Generated with `php artisan laragraph:make:exception InvalidCredentialsException`;
 * its message lives under the `errors.invalid_credentials` key in
 * lang/{locale}/errors.php, so it comes back translated per the negotiated
 * request locale — see config/laragraph.php's `errors.negotiate_locale`.
 */
class InvalidCredentialsException extends GraphQLException
{
    /**
     * @param  array<string, mixed>  $replace
     * @param  array<string, mixed>  $extra
     */
    public function __construct(array $replace = [], array $extra = [])
    {
        parent::__construct(
            key: 'errors.invalid_credentials',
            errorCode: 'INVALID_CREDENTIALS',
            replace: $replace,
            extra: $extra,
            category: 'authentication',
        );
    }
}
