<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Gate;

class UserQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('User');
    }

    public function args(): array
    {
        return ['id' => ['type' => Type::nonNull(Type::id())]];
    }

    /**
     * authorize() runs before validation and the resolver; returning false
     * produces an error with extensions.category = "authorization".
     */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $user = User::find($args['id']);

        return $user !== null && Gate::allows('view', $user);
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::findOrFail($args['id']);
    }
}
