<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class LoginPayloadType extends Type
{
    protected array $attributes = [
        'name' => 'LoginPayload',
        'description' => 'A JWT for the `Authorization: Bearer` header, and the user it belongs to.',
    ];

    public function fields(): array
    {
        return [
            'token' => GType::nonNull(GType::string()),
            'expiresIn' => ['type' => GType::nonNull(GType::int()), 'description' => 'Seconds until the token expires.'],
            'user' => GType::nonNull(Laragraph::type('User')),
        ];
    }
}
