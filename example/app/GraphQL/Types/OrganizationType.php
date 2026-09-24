<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Enums\PostStatus;
use App\GraphQL\Loaders\MemberCountLoader;
use App\Models\Organization;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Support\Collection;

class OrganizationType extends Type
{
    protected array $attributes = [
        'name' => 'Organization',
        'description' => 'A team that users belong to and publish posts in.',
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
            'name' => GType::nonNull(GType::string()),
            'slug' => GType::nonNull(GType::string()),
            'city' => GType::string(),
            'country' => GType::string(),
            'description' => GType::string(),

            // The JSON scalar passes arbitrary structured data through.
            'settings' => Laragraph::type('JSON'),

            'owner' => Laragraph::type('User'),
            'members' => GType::nonNull(GType::listOf(GType::nonNull(Laragraph::type('User')))),
            'memberCount' => GType::nonNull(GType::int()),
            'posts' => [
                'type' => GType::nonNull(GType::listOf(GType::nonNull(Laragraph::type('Post')))),
                'description' => 'Published posts only.',
            ],
        ];
    }

    protected function resolveOwnerField(Organization $organization, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Organization::class, 'owner', $organization, $context);
    }

    protected function resolveMembersField(Organization $organization, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Organization::class, 'members', $organization, $context);
    }

    /**
     * A custom DataLoader: every organization in the response shares one
     * grouped COUNT query. The registry is reached through $context.
     */
    protected function resolveMemberCountField(Organization $organization, array $args, mixed $context): mixed
    {
        return DataLoaderRegistry::for($context)->get(MemberCountLoader::class)->load($organization->id);
    }

    protected function resolvePostsField(Organization $organization, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Organization::class, 'posts', $organization, $context)
            ->then(fn (Collection $posts): Collection => $posts->where('status', PostStatus::Published)->values());
    }
}
