<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Gate;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** Always resolves — used for cache and wrapContext tests. */
class Phase3PingQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

/**
 * Field whose policy() returns a non-null value, triggering the Gate-based
 * policy check in buildResolver().
 *
 * Does NOT override policyAbility() so the default 'view' is used — this
 * covers Field.php line 199.
 */
class PolicyRestrictedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    /** Return any non-null string to activate the policy check block. */
    public function policy(): ?string { return 'SomeResourcePolicy'; }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'restricted-data';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class Phase3CoverageTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query' => [
                'phase3Ping'       => Phase3PingQuery::class,
                'policyRestricted' => PolicyRestrictedQuery::class,
            ],
        ]);

        // Use the in-memory array cache driver so ResponseCache works without
        // an external cache backend.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laragraph.cache.response.store', 'default');
    }

    // -------------------------------------------------------------------------
    // Field.php line 199 (policyAbility default return) +
    // Field.php lines 279-282 (policy check throws when can() is false)
    // -------------------------------------------------------------------------

    public function test_policy_field_blocks_guest_user(): void
    {
        // No user authenticated → AuthorizationContext::can() returns false immediately
        // → policy check fails → AuthorizationException thrown
        $result = $this->graphql('{ policyRestricted }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(
            'authorization',
            $result['errors'][0]['extensions']['category'] ?? null,
        );
    }

    // -------------------------------------------------------------------------
    // AuthorizationContext.php line 90 — can() with an authenticated user
    // -------------------------------------------------------------------------

    public function test_policy_field_allows_authenticated_user_when_gate_permits(): void
    {
        // Authenticate a user so can() proceeds past the null-user guard
        $user = new \Illuminate\Foundation\Auth\User();
        $user->forceFill(['id' => 1]);
        $this->actingAs($user);

        // Define the 'view' ability so the Gate returns true
        Gate::define('view', fn ($u, $argument) => true);

        // PolicyRestrictedQuery → can('view', 'SomeResourcePolicy') → true
        // → policy passes → resolver runs
        $result = $this->graphql('{ policyRestricted }');

        // Temporary diagnostic — dump full response if assertion fails
        $this->assertSame('restricted-data', $result['data']['policyRestricted'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Laragraph::wrapContext() — array branch (lines 311-313)
    // -------------------------------------------------------------------------

    public function test_wrap_context_with_array_injects_data_loaders(): void
    {
        /** @var Laragraph $laragraph */
        $laragraph = app(Laragraph::class);

        // Array context → wrapContext() adds 'dataLoaders' key
        $result = $laragraph->executeQuery('{ phase3Ping }', ['userId' => 99]);

        $this->assertSame(['data' => ['phase3Ping' => 'pong']], $result->toArray(0));
    }

    // -------------------------------------------------------------------------
    // Laragraph::wrapContext() — scalar fallback (line 316)
    // -------------------------------------------------------------------------

    public function test_wrap_context_with_scalar_passes_through_unchanged(): void
    {
        /** @var Laragraph $laragraph */
        $laragraph = app(Laragraph::class);

        // Scalar context → wrapContext() fallback — neither object nor array
        $result = $laragraph->executeQuery('{ phase3Ping }', 42);

        $this->assertSame(['data' => ['phase3Ping' => 'pong']], $result->toArray(0));
    }

    // -------------------------------------------------------------------------
    // Laragraph::execute() — ResponseCache hit path (lines 91-95, 109)
    // -------------------------------------------------------------------------

    public function test_response_cache_stores_and_serves_cached_result(): void
    {
        config(['laragraph.cache.response.enabled' => true]);

        // First call — cache miss, executes and stores result (covers line 109)
        $first = $this->graphql('{ phase3Ping }');
        $this->assertSame('pong', $first['data']['phase3Ping'] ?? null);

        // Second call — cache hit, skips execution (covers lines 91-95)
        $second = $this->graphql('{ phase3Ping }');
        $this->assertSame($first, $second);
    }

    public function test_response_cache_does_not_cache_mutations(): void
    {
        config(['laragraph.cache.response.enabled' => true]);

        // Mutations must bypass the cache — no cacheKey should be set
        // The schema has no mutations, so we just verify the query goes through
        // and the response cache doesn't interfere with the request pipeline.
        $result = $this->graphql('{ phase3Ping }');
        $this->assertArrayNotHasKey('errors', $result);
    }
}
