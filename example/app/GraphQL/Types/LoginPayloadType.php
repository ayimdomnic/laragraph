<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class LoginPayloadType extends Type
{
    protected array $attributes = [
        'name' => 'LoginPayload',
        'description' => 'Return payload for login mutation.',
    ];

    public function fields(): array
    {
        return [
            'token' => ['type' => GType::nonNull(GType::string())],
            'user' => ['type' => app('laragraph')->type('User')],
            'refreshToken' => ['type' => GType::string()],
        ];
    }
}
