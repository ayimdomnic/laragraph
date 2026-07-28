<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

use GraphQL\Error\ClientAware;
use Illuminate\Contracts\Validation\Validator;

/**
 * Thrown when a resolver's validation rules fail.
 *
 * The validation errors are exposed to the client via the GraphQL error
 * extensions under the 'validation' key.
 */
class ValidationException extends \RuntimeException implements ClientAware
{
    public function __construct(protected readonly Validator $validator)
    {
        parent::__construct('Validation failed.');
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Return the validation error messages keyed by field name.
     *
     * @return array<string, array<string>>
     */
    public function getValidationErrors(): array
    {
        return $this->validator->errors()->toArray();
    }

    public function getValidator(): Validator
    {
        return $this->validator;
    }
}
