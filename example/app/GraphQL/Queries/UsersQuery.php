<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `users` — admins only, via the policy() shortcut, as a Relay connection.
 */
class UsersQuery extends Query
{
    /** Checks UserPolicy::viewAny() through the Gate before resolving. */
    public function policy(): ?string
    {
        return User::class;
    }

    public function policyAbility(): string
    {
        return 'viewAny';
    }

    public function type(): Type
    {
        return ConnectionType::make('UserConnection', Laragraph::type('User'));
    }

    public function args(): array
    {
        return [
            ...ConnectionType::args(), // first, after, last, before
            'role' => ['type' => Laragraph::type('UserRole'), 'description' => 'Only users with this role.'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $query = User::query()
            ->where('organization_id', $context->user()->organization_id)
            ->orderBy('id');

        // Enum arguments arrive as enum cases (App\Enums\UserRole), not strings.
        if (isset($args['role'])) {
            $query->where('role', $args['role']);
        }

        return ConnectionType::paginate($query, $args);
    }
}
