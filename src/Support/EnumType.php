<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\EnumType as GraphQLEnumType;

/**
 * Base class for GraphQL Enum Types.
 *
 * Usage:
 *
 *   class UserStatusEnum extends EnumType
 *   {
 *       protected array $attributes = [
 *           'name'        => 'UserStatus',
 *           'description' => 'The status of a user account.',
 *       ];
 *
 *       public function values(): array
 *       {
 *           return [
 *               'ACTIVE'   => ['value' => 'active',   'description' => 'Active account'],
 *               'INACTIVE' => ['value' => 'inactive', 'description' => 'Disabled account'],
 *               'BANNED'   => ['value' => 'banned',   'description' => 'Banned account'],
 *           ];
 *       }
 *   }
 *
 * You can also return the cases of a native PHP enum; each case becomes a
 * GraphQL value named after the case, and resolvers receive the case itself:
 *
 *   public function values(): array
 *   {
 *       return UserStatus::cases();
 *   }
 *
 * (Or skip the wrapper class entirely and register the enum directly —
 * `'types' => ['UserStatus' => UserStatus::class]` — see the README.)
 */
abstract class EnumType extends GraphQLEnumType
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    public function __construct()
    {
        $config = array_merge(
            ['name' => class_basename(static::class)],
            $this->attributes,
            ['values' => $this->normalizeValues($this->values())],
        );

        parent::__construct($config);
    }

    /**
     * Return the enum values.
     *
     * Each key is the GraphQL enum value name; value is either a scalar or
     * an array with 'value', 'description', 'deprecationReason' keys.
     *
     * @return array<string, mixed>
     */
    abstract public function values(): array;

    /**
     * Expand a list of native enum cases into GraphQL value definitions.
     *
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_int($key) && $value instanceof \UnitEnum) {
                $normalized[$value->name] = ['value' => $value];

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
