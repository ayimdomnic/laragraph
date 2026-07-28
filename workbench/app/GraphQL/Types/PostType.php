<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Types;

use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class PostType extends Type
{
    protected array $attributes = [
        'name'        => 'Post',
        'description' => 'A blog post.',
    ];

    public function fields(): array
    {
        return [
            'id'           => ['type' => GType::nonNull(GType::id())],
            'title'        => ['type' => GType::string()],
            'body'         => ['type' => GType::string()],
            'published_at' => ['type' => GType::string()],
        ];
    }
}
