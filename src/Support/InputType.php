<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\InputObjectType;

/**
 * Base class for GraphQL Input Object Types.
 *
 * Input types describe the shape of mutation / query arguments that are
 * complex objects, rather than simple scalars.
 *
 * Usage:
 *
 *   class CreateUserInput extends InputType
 *   {
 *       protected array $attributes = [
 *           'name'        => 'CreateUserInput',
 *           'description' => 'Input for creating a new user.',
 *       ];
 *
 *       public function fields(): array
 *       {
 *           return [
 *               'name'  => ['type' => Type::nonNull(Type::string())],
 *               'email' => ['type' => Type::nonNull(Type::string())],
 *           ];
 *       }
 *   }
 */
abstract class InputType extends InputObjectType
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
            [
                'fields' => function (): array {
                    return $this->fields();
                },
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the input field definitions.
     *
     * @return array<string, mixed>
     */
    abstract public function fields(): array;
}
