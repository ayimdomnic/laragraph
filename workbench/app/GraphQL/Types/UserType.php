<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Types;

use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class UserType extends Type
{
    protected array $attributes = [
        'name'        => 'User',
        'description' => 'A registered user.',
    ];

    public function fields(): array
    {
        return [
            'id'       => ['type' => GType::nonNull(GType::id())],
            'name'     => ['type' => GType::string()],
            'email'    => ['type' => GType::string()],
            'age'      => ['type' => GType::int()],
            'is_admin' => ['type' => GType::boolean()],
        ];
    }
}
