<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class SbQuery extends Query
{
    public function type(): GType { return GType::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'query-result';
    }
}

class SbMutation extends Mutation
{
    public function type(): GType { return GType::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'mutation-result';
    }
}

class SbSubscription extends Subscription
{
    public function type(): GType { return GType::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

/** A Type that belongs to an object type — used to test the typeLoader. */
class SbNodeType extends Type
{
    protected array $attributes = ['name' => 'SbNode'];
    public function fields(): array
    {
        return ['id' => ['type' => GType::nonNull(GType::id())]];
    }
}

/** A Query that returns an SbNode so the typeLoader is exercised. */
class SbNodeQuery extends Query
{
    public function type(): GType
    {
        return app('laragraph')->type('SbNode');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['id' => '1'];
    }
}

/** A Query field with a custom complexity cost. */
class SbCostlyQuery extends Query
{
    public function type(): GType { return GType::string(); }

    public function complexity(): int
    {
        return 5;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'costly';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SchemaBuilderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // typeLoader closure is invoked for types added after schema build
    // -------------------------------------------------------------------------

    public function test_type_loader_is_called_for_post_build_registered_type(): void
    {
        $this->app['config']->set('laragraph.schemas.default', [
            'query' => ['sb' => SbQuery::class],
        ]);
        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        $manager = $this->app->make(\Ayimdomnic\Laragraph\Laragraph::class);
        $schema  = $manager->schema(); // build schema (typeMap is frozen)

        // Register a type AFTER schema build — not in typeMap yet
        $manager->addType(SbNodeType::class, 'SbNode');

        // Schema::getType() → typeLoader closure fires for unknown names
        $resolved = $schema->getType('SbNode');
        $this->assertNotNull($resolved);
        $this->assertSame('SbNode', $resolved->name);
    }

    // -------------------------------------------------------------------------
    // Subscription field in schema
    // -------------------------------------------------------------------------

    public function test_schema_with_subscription_type_is_built(): void
    {
        $this->app['config']->set('laragraph.schemas.default', [
            'query'        => ['sb' => SbQuery::class],
            'subscription' => ['sbSub' => SbSubscription::class],
        ]);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        $schema = $this->app->make(Laragraph::class)->schema();
        $this->assertNotNull($schema->getSubscriptionType());
    }

    // -------------------------------------------------------------------------
    // typeLoader is invoked when resolving a named type
    // -------------------------------------------------------------------------

    public function test_type_loader_resolves_registered_type(): void
    {
        $this->app['config']->set('laragraph.schemas.default', [
            'query' => ['sbNode' => SbNodeQuery::class],
            'types' => [SbNodeType::class],
        ]);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        $manager = $this->app->make(Laragraph::class);
        $manager->addType(SbNodeType::class, 'SbNode');

        $result = $manager->execute('{ sbNode { id } }');
        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('1', $result['data']['sbNode']['id']);
    }

    // -------------------------------------------------------------------------
    // Field complexity cost is wired up in buildFields
    // -------------------------------------------------------------------------

    public function test_field_with_complexity_is_built(): void
    {
        $this->app['config']->set('laragraph.schemas.default', [
            'query' => ['sbCostly' => SbCostlyQuery::class],
        ]);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        // With high complexity limit the query should succeed
        config(['laragraph.security.query_max_complexity' => 100]);
        $result = $this->app->make(Laragraph::class)->execute('{ sbCostly }');
        $this->assertSame('costly', $result['data']['sbCostly']);
    }

    // -------------------------------------------------------------------------
    // discoverFields returns [] when path is explicitly empty
    // -------------------------------------------------------------------------

    public function test_discover_fields_returns_empty_for_empty_path(): void
    {
        // Set all discover paths to empty so discoverFields hits the early-return
        $this->app['config']->set('laragraph.discover', [
            'types'         => '',
            'queries'       => '',
            'mutations'     => '',
            'subscriptions' => '',
        ]);

        $this->app['config']->set('laragraph.schemas.default', [
            'query' => ['sb' => SbQuery::class],
        ]);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        // Schema still builds — just no auto-discovered fields
        $result = $this->app->make(Laragraph::class)->execute('{ sb }');
        $this->assertSame('query-result', $result['data']['sb']);
    }
}
