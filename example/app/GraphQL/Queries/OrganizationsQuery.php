<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use App\Models\Organization;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class OrganizationsQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('OrganizationConnection', app('laragraph')->type('Organization'));
    }

    public function args(): array
    {
        return ConnectionType::args();
    }

    public function description(): ?string
    {
        return 'Paginated list of organization records.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ConnectionType::paginate(Organization::query(), $args);
    }
}
