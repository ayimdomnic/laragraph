<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Only admins may list users (used by UsersQuery::policy()). */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Admins see members of their organization; everyone sees themselves. */
    public function view(User $user, User $target): bool
    {
        return $user->is($target) || ($user->isAdmin() && $user->organization_id === $target->organization_id);
    }
}
