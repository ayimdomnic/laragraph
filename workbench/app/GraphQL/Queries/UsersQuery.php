<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Workbench\App\Models\User;

class UsersQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('UserConnection', app('laragraph')->type('User'));
    }

    public function args(): array
    {
        return ConnectionType::args();
    }

    public function description(): ?string
    {
        return 'Paginated list of users.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ConnectionType::paginate(User::query(), $args);
    }
}
