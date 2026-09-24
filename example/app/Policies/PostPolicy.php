<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /** Guests may read published posts, so the user is nullable. */
    public function view(?User $user, Post $post): bool
    {
        return $post->status === PostStatus::Published
            || ($user !== null && ($post->user_id === $user->id || $this->administers($user, $post)));
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $this->administers($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    private function administers(User $user, Post $post): bool
    {
        return $user->isAdmin() && $user->organization_id === $post->organization_id;
    }
}
