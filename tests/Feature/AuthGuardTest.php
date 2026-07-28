<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Auth\AuthorizationContext;
use Ayimdomnic\Laragraph\Auth\GuardResolver;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** Public field — no auth required. */
class PublicQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'public';
    }
}

/** Blocked via authorizeWithContext(). */
class ContextBlockedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        return false;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'secret';
    }
}

/** Allowed via authorizeWithContext(). */
class ContextAllowedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        return true;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'allowed';
    }
}

/** Field with a specific guard declared. */
class GuardedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function guards(): array { return ['web']; }

    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        // In the test, no user is authenticated → check() = false
        return $ctx->check();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'guarded';
    }
}

/** Field with a deprecated notice. */
class DeprecatedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function deprecated(): ?string { return 'Use `newQuery` instead.'; }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'old';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class AuthGuardTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query' => [
                'public'          => PublicQuery::class,
                'contextBlocked'  => ContextBlockedQuery::class,
                'contextAllowed'  => ContextAllowedQuery::class,
                'guarded'         => GuardedQuery::class,
                'deprecated'      => DeprecatedQuery::class,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Public field passes without any auth
    // -------------------------------------------------------------------------

    public function test_public_field_resolves_without_auth(): void
    {
        $result = $this->graphql('{ public }');
        $this->assertSame('public', $result['data']['public']);
    }

    // -------------------------------------------------------------------------
    // authorizeWithContext returning false → AuthorizationException
    // -------------------------------------------------------------------------

    public function test_context_blocked_field_returns_authorization_error(): void
    {
        $result = $this->graphql('{ contextBlocked }');
        $this->assertArrayHasKey('errors', $result);
        $this->assertSame('authorization', $result['errors'][0]['extensions']['category'] ?? null);
    }

    // -------------------------------------------------------------------------
    // authorizeWithContext returning true → resolves normally
    // -------------------------------------------------------------------------

    public function test_context_allowed_field_resolves(): void
    {
        $result = $this->graphql('{ contextAllowed }');
        $this->assertSame('allowed', $result['data']['contextAllowed']);
    }

    // -------------------------------------------------------------------------
    // Field with guard — no user → authorization error
    // -------------------------------------------------------------------------

    public function test_guarded_field_without_authenticated_user_is_blocked(): void
    {
        $result = $this->graphql('{ guarded }');
        $this->assertArrayHasKey('errors', $result);
    }

    // -------------------------------------------------------------------------
    // Deprecated field carries deprecationReason in schema
    // -------------------------------------------------------------------------

    public function test_deprecated_field_carries_deprecation_reason(): void
    {
        $schema = $this->app->make(\Ayimdomnic\Laragraph\Laragraph::class)->schema();
        $field  = $schema->getQueryType()->getField('deprecated');

        $this->assertSame('Use `newQuery` instead.', $field->deprecationReason);
    }

    // -------------------------------------------------------------------------
    // GuardResolver
    // -------------------------------------------------------------------------

    public function test_guard_resolver_uses_field_guard_over_default(): void
    {
        config(['laragraph.auth.default_guard' => 'web']);
        $this->assertSame('sanctum', GuardResolver::resolve('sanctum'));
    }

    public function test_guard_resolver_falls_back_to_config_default(): void
    {
        config(['laragraph.auth.default_guard' => 'api']);
        $this->assertSame('api', GuardResolver::resolve(null));
    }

    public function test_guard_resolver_returns_null_when_no_guard_configured(): void
    {
        config(['laragraph.auth.default_guard' => null]);
        $this->assertNull(GuardResolver::resolve(null));
    }

    // -------------------------------------------------------------------------
    // AuthorizationContext
    // -------------------------------------------------------------------------

    public function test_authorization_context_check_returns_false_for_guest(): void
    {
        $ctx = GuardResolver::buildContext(null);
        $this->assertFalse($ctx->check());
        $this->assertNull($ctx->user());
    }

    public function test_authorization_context_can_returns_false_for_guest(): void
    {
        $ctx = GuardResolver::buildContext(null);
        $this->assertFalse($ctx->can('view', 'SomeModel'));
    }

    public function test_authorization_context_exposes_guard_name(): void
    {
        $ctx = GuardResolver::buildContext('sanctum');
        $this->assertSame('sanctum', $ctx->guardName());
    }

    public function test_authorization_context_exposes_request(): void
    {
        $ctx = GuardResolver::buildContext(null);
        $this->assertInstanceOf(\Illuminate\Http\Request::class, $ctx->request());
    }
}
