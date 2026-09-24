<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Facades;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValidationRule;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Schema schema(?string $name = null)
 * @method static array<string, mixed> execute(string $query, mixed $context = null, array<string, mixed> $variables = [], ?string $operationName = null, ?string $schemaName = null, mixed $rootValue = null)
 * @method static array<int, array<string, mixed>> executeBatch(array<int, array<string, mixed>> $operations, mixed $context = null, string $schemaName = 'default')
 * @method static Type|null typeByName(string $graphqlName)
 * @method static int broadcast(string $channel, mixed $payload = null)
 * @method static void broadcastLater(string $channel, mixed $payload = null)
 * @method static bool unsubscribe(string $subscriberId)
 * @method static string addType(string|Type $class, ?string $alias = null)
 * @method static Type type(string $name, bool $fresh = false)
 * @method static bool hasType(string $name)
 * @method static void addValidationRule(string|ValidationRule $rule)
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
