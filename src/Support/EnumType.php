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
 * You can also use PHP 8.1 backed enums:
 *
 *   public function values(): array
 *   {
 *       return UserStatus::cases(); // backed enum
 *   }
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
            ['values' => $this->values()],
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
}
