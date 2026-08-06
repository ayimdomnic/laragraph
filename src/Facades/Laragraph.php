<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GraphQL\Type\Schema schema(?string $name = null)
 * @method static array execute(string $query, mixed $context = null, array $variables = [], ?string $operationName = null, ?string $schemaName = null, mixed $rootValue = null)
 * @method static array executeBatch(array $operations, mixed $context = null, string $schemaName = 'default')
 * @method static int broadcast(string $channel, mixed $payload = null)
 * @method static void addType(string|\GraphQL\Type\Definition\Type $class, ?string $alias = null)
 * @method static \GraphQL\Type\Definition\Type type(string $name, bool $fresh = false)
 * @method static bool hasType(string $name)
 * @method static void addValidationRule(string|\GraphQL\Validator\Rules\ValidationRule $rule)
 *
 * @see \Ayimdomnic\Laragraph\Laragraph
 */
class Laragraph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laragraph';
    }
}
