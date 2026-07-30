<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Support\Query;
use App\Models\Organization;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class OrganizationQuery extends Query
{
    public function type(): Type
    {
        return app('laragraph')->type('Organization');
    }

    public function args(): array
    {
        return [
            'id' => ['type' => Type::nonNull(Type::id()), 'description' => 'The organization ID.'],
        ];
    }

    public function description(): ?string
    {
        return 'Fetch a single organization by ID.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return Organization::findOrFail($args['id']);
    }
}
