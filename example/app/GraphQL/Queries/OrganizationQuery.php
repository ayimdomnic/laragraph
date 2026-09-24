<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Organization;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class OrganizationQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('Organization');
    }

    public function args(): array
    {
        return [
            'id' => ['type' => Type::id()],
            'slug' => ['type' => Type::string()],
        ];
    }

    /**
     * Laravel validation rules run against $args before the resolver.
     */
    public function rules(array $args = []): array
    {
        return [
            'id' => ['required_without:slug'],
            'slug' => ['required_without:id', 'string'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return isset($args['id'])
            ? Organization::find($args['id'])
            : Organization::where('slug', $args['slug'])->first();
    }
}
