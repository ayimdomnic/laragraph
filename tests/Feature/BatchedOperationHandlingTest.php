<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class BatchedPingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

class BatchedTickSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'ticks';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class BatchedOperationHandlingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.batching.enabled', true);
        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('laragraph.persisted_queries.enabled', true);
        $app['config']->set('laragraph.persisted_queries.store', 'array');
        $app['config']->set('laragraph.persisted_queries.map', [hash('sha256', '{ ping }') => '{ ping }']);
        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['ping' => BatchedPingQuery::class],
            'subscription' => ['tick' => BatchedTickSubscription::class],
        ]);
    }

    public function test_batched_operations_get_the_same_handling_as_single_requests(): void
    {
        $results = $this->postJson('/graphql', [
            ['query' => '{ ping }'],
            ['extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => hash('sha256', '{ ping }')]]],
            ['extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => str_repeat('0', 64)]]],
            ['query' => 'subscription { tick }'],
        ])->assertOk()->json();

        $this->assertSame('pong', $results[0]['data']['ping']);
        $this->assertSame('pong', $results[1]['data']['ping']);
        $this->assertSame('PERSISTED_QUERY_NOT_FOUND', $results[2]['errors'][0]['extensions']['code']);
        $this->assertSame('ticks', $results[3]['extensions']['subscription']['channel']);
    }

    public function test_the_programmatic_batch_api_still_executes_operations(): void
    {
        $results = Laragraph::executeBatch([['query' => '{ ping }'], ['query' => '{ ping }']]);

        $this->assertSame(['pong', 'pong'], array_column(array_column($results, 'data'), 'ping'));
    }
}
