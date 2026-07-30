<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

class UserQuery extends Query
{
    public function type(): GType
    {
        return app('laragraph')->type('User');
    }

    public function args(): array
    {
        return [
            'id' => ['type' => GType::nonNull(GType::id())],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::find($args['id']);
    }
}
