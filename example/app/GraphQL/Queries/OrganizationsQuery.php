<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Organization;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `organizations` — public, Relay-paginated.
 */
class OrganizationsQuery extends Query
{
    public function type(): Type
    {
        return ConnectionType::make('OrganizationConnection', Laragraph::type('Organization'));
    }

    public function args(): array
    {
        return ConnectionType::args();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ConnectionType::paginate(Organization::query()->orderBy('name'), $args);
    }
}
