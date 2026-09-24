<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use Ayimdomnic\Laragraph\Support\InputType;
use GraphQL\Type\Definition\Type as GType;

/**
 * INPUT TYPE — the structured argument of `createPost(input: PostInput!)`.
 */
class PostInputType extends InputType
{
    protected array $attributes = [
        'name' => 'PostInput',
        'description' => 'The content of a new post.',
    ];

    public function fields(): array
    {
        return [
            'title' => ['type' => GType::nonNull(GType::string())],
            'body' => ['type' => GType::nonNull(GType::string())],
            'publish' => [
                'type' => GType::boolean(),
                'defaultValue' => false,
                'description' => 'Publish immediately (notifies `postPublished` subscribers).',
            ],
        ];
    }
}
