<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\ObjectType;

/**
 * Base class for GraphQL Object Types.
 *
 * Usage:
 *
 *   class UserType extends Type
 *   {
 *       protected array $attributes = [
 *           'name'        => 'User',
 *           'description' => 'A registered user.',
 *       ];
 *
 *       public function fields(): array
 *       {
 *           return [
 *               'id'    => ['type' => Type::nonNull(Type::id())],
 *               'name'  => ['type' => Type::string()],
 *               'email' => ['type' => Type::string()],
 *           ];
 *       }
 *
 *       // Optional per-field resolvers: resolve{FieldName}Field($root, $args)
 *       protected function resolveEmailField(mixed $root, array $args): string
 *       {
 *           return strtolower($root->email);
 *       }
 *   }
 */
abstract class Type extends ObjectType
{
    /**
     * Describe the type using 'name' and optionally 'description' and
     * 'interfaces'.
     *
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
                    return $this->buildFields();
                },
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the field definitions for this type.
     *
     * Each entry may be:
     *  - A full field config array: ['type' => ..., 'description' => ..., ...]
     *  - A shorthand Type instance: \GraphQL\Type\Definition\Type::string()
     *
     * @return array<string, mixed>
     */
    abstract public function fields(): array;

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function buildFields(): array
    {
        $fields = $this->fields();

        foreach ($fields as $name => &$field) {
            // Shorthand: if value is a Type instance, wrap it
            if ($field instanceof \GraphQL\Type\Definition\Type) {
                $field = ['type' => $field];
            }

            // Auto-attach per-field resolver methods: resolveEmailField, etc.
            if (!isset($field['resolve'])) {
                $method = 'resolve' . ucfirst($name) . 'Field';
                if (method_exists($this, $method)) {
                    $field['resolve'] = [$this, $method];
                }
            }
        }

        return $fields;
    }
}
