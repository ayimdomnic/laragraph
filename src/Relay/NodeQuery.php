<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Relay;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type as LaragraphType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Relay's `node(id: ID!): Node` root field.
 *
 * Register it under `query.node` in a schema's config (or place a subclass
 * under `app/GraphQL/Queries` for auto-discovery). Decodes the global id via
 * {@see GlobalId}, looks up the named type, and delegates to its
 * {@see LaragraphType::resolveNode()} — types that don't override that hook
 * (the default) simply aren't re-fetchable, and this returns null rather
 * than erroring.
 */
class NodeQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('Node');
    }

    public function args(): array
    {
        return ['id' => ['type' => Type::nonNull(Type::id())]];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $decoded = GlobalId::decode((string) $args['id']);

        if ($decoded === null || !Laragraph::hasType($decoded['type'])) {
            return null;
        }

        $type = Laragraph::type($decoded['type']);

        return $type instanceof LaragraphType ? $type->resolveNode($decoded['id'], $context) : null;
    }
}
