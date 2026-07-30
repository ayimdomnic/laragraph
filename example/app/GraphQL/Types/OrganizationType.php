<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class OrganizationType extends Type
{
    protected array $attributes = [
        'name'        => 'Organization',
        'description' => 'A organization resource.',
    ];

    public function fields(): array
    {
        return [
            'id' => ['type' => GType::nonNull(GType::id())],
            'name' => ['type' => GType::string()],
            'email' => ['type' => GType::string()],
            'address' => ['type' => GType::string()],
            'phone' => ['type' => GType::string()],
            'city' => ['type' => GType::string()],
            'state' => ['type' => GType::string()],
            'country' => ['type' => GType::string()],
            'zip_code' => ['type' => GType::string()],
            'description' => ['type' => GType::string()],
            'status' => ['type' => GType::string()],
            'settings' => ['type' => app('laragraph')->type('JSON')],
            'type' => ['type' => GType::string()],
            'parent_id' => ['type' => GType::string()],
            'owner_id' => ['type' => GType::string()],
        ];
    }
}
