<?php

declare(strict_types=1);

namespace App\GraphQL\Admin;

use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

/**
 * Registered only under schemas.admin.types, so it exists in the admin
 * schema and is invisible (even to introspection) on the public one.
 */
class AdminStatsType extends Type
{
    protected array $attributes = [
        'name' => 'AdminStats',
        'description' => 'Organization-wide numbers for administrators.',
    ];

    public function fields(): array
    {
        return [
            'members' => GType::nonNull(GType::int()),
            'publishedPosts' => GType::nonNull(GType::int()),
            'draftPosts' => GType::nonNull(GType::int()),
        ];
    }
}
