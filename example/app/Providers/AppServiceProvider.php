<?php

declare(strict_types=1);

namespace App\Providers;

use App\GraphQL\Extensions\ApiVersionExtension;
use App\Listeners\FlushResponseCacheAfterMutations;
use App\Listeners\LogSlowGraphQLOperations;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Policies\UserPolicy;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Policies used by authorize(), the policy() shortcut and field resolvers.
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Guards the admin schema (see schemas.admin.middleware in config/laragraph.php).
        Gate::define('access-admin-api', fn (User $user): bool => $user->isAdmin());

        // A custom `extensions.apiVersion` entry on every GraphQL response.
        $this->app->make(ExtensionRegistry::class)->add(new ApiVersionExtension);

        // React to GraphQL lifecycle events.
        Event::listen(QueryExecuted::class, LogSlowGraphQLOperations::class);
        Event::listen(QueryExecuted::class, FlushResponseCacheAfterMutations::class);
    }
}
