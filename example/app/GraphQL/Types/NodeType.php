<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\InterfaceType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

/**
 * INTERFACE — every entity with a stable id implements `Node`.
 *
 * Discovered from app/GraphQL/Types and registered under the alias "Node"
 * (the class name minus its "Type" suffix).
 */
class NodeType extends InterfaceType
{
    protected array $attributes = [
        'name' => 'Node',
        'description' => 'An object with a globally unique ID.',
    ];

    public function fields(): array
    {
        return [
            'id' => ['type' => GType::nonNull(GType::id())],
        ];
    }

    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return match (true) {
            $value instanceof User => Laragraph::type('User'),
            $value instanceof Post => Laragraph::type('Post'),
            $value instanceof Organization => Laragraph::type('Organization'),
            default => null,
        };
    }
}
