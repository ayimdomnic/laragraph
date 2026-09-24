<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Support\Str;

class PostType extends Type
{
    protected array $attributes = [
        'name' => 'Post',
        'description' => 'An article written by a user for their organization.',
    ];

    public function __construct()
    {
        $this->attributes['interfaces'] = fn (): array => [Laragraph::type('Node')];

        parent::__construct();
    }

    public function fields(): array
    {
        return [
            'id' => GType::nonNull(GType::id()),
            'title' => GType::nonNull(GType::string()),
            'body' => GType::nonNull(GType::string()),

            // A field with its own arguments.
            'excerpt' => [
                'type' => GType::nonNull(GType::string()),
                'args' => [
                    'length' => ['type' => GType::int(), 'defaultValue' => 120],
                ],
            ],

            // A deprecated field: still works, flagged in introspection and GraphiQL.
            'summary' => [
                'type' => GType::string(),
                'deprecationReason' => 'Use `excerpt` instead.',
            ],

            'status' => GType::nonNull(Laragraph::type('PostStatus')),
            'publishedAt' => Laragraph::type('DateTime'),
            'author' => GType::nonNull(Laragraph::type('User')),
            'organization' => GType::nonNull(Laragraph::type('Organization')),
        ];
    }

    /**
     * @param  array{length: int}  $args
     */
    protected function resolveExcerptField(Post $post, array $args): string
    {
        return Str::limit($post->body, max(10, $args['length']));
    }

    protected function resolveSummaryField(Post $post): string
    {
        return Str::limit($post->body, 120);
    }

    protected function resolvePublishedAtField(Post $post): mixed
    {
        return $post->published_at;
    }

    protected function resolveAuthorField(Post $post, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Post::class, 'author', $post, $context);
    }

    protected function resolveOrganizationField(Post $post, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Post::class, 'organization', $post, $context);
    }
}
