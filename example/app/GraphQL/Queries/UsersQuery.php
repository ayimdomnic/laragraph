<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

class UsersQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('User'));
    }

    public function args(): array
    {
        return [
            'limit' => ['type' => GType::int(), 'defaultValue' => 10],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::limit($args['limit'])->get();
    }
}
