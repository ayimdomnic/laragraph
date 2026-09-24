<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * OBJECT TYPE — shows per-field resolvers, native enums, custom scalars,
 * interfaces and N+1-safe relations.
 */
class UserType extends Type
{
    protected array $attributes = [
        'name' => 'User',
        'description' => 'A person who belongs to an organization.',
    ];

    public function __construct()
    {
        // Interfaces are resolved lazily, after every type is registered.
        $this->attributes['interfaces'] = fn (): array => [Laragraph::type('Node')];

        parent::__construct();
    }

    public function fields(): array
    {
        return [
            'id' => GType::nonNull(GType::id()),
            'name' => GType::nonNull(GType::string()),

            // Private: only the user themselves and their organization's admins see it.
            'email' => [
                'type' => GType::string(),
                'description' => 'Visible to the user and their organization admins; null for everyone else.',
            ],

            // A native PHP enum (App\Enums\UserRole), registered in config/laragraph.php.
            'role' => GType::nonNull(Laragraph::type('UserRole')),

            'avatarUrl' => GType::string(),
            'createdAt' => Laragraph::type('DateTime'),
            'organization' => Laragraph::type('Organization'),
            'posts' => [
                'type' => GType::nonNull(GType::listOf(GType::nonNull(Laragraph::type('Post')))),
                'description' => 'Posts by this user that the viewer may read.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Per-field resolvers: resolve{FieldName}Field() is wired up automatically.
    // -------------------------------------------------------------------------

    protected function resolveEmailField(User $user): ?string
    {
        return Gate::allows('view', $user) ? $user->email : null;
    }

    protected function resolveAvatarUrlField(User $user): ?string
    {
        return $user->avatar_path === null ? null : Storage::disk('public')->url($user->avatar_path);
    }

    protected function resolveCreatedAtField(User $user): mixed
    {
        return $user->created_at;
    }

    /**
     * batchRelation() collapses N parents into ONE query for the relation,
     * however many users the query returns.
     */
    protected function resolveOrganizationField(User $user, array $args, mixed $context): mixed
    {
        return $this->batchRelation(User::class, 'organization', $user, $context);
    }

    protected function resolvePostsField(User $user, array $args, mixed $context): mixed
    {
        // The batched promise can be post-processed: drop drafts the viewer may not read.
        return $this->batchRelation(User::class, 'posts', $user, $context)
            ->then(fn (Collection $posts): Collection => $posts->filter(fn ($post): bool => Gate::allows('view', $post))->values());
    }
}
