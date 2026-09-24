<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class RequestIdPingQuery extends Query
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

class BatchRequestIdAndAliasesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.batching.enabled', true);
        $app['config']->set('laragraph.extensions.request_id', true);
        $app['config']->set('laragraph.security.max_aliases', 3);
        $app['config']->set('laragraph.schemas.default.query', ['ping' => RequestIdPingQuery::class]);
    }

    public function test_every_operation_in_a_batch_reports_the_same_request_id(): void
    {
        $results = $this->postJson('/graphql', [['query' => '{ ping }'], ['query' => '{ ping }']])->json();

        $this->assertSame($results[0]['extensions']['requestId']['id'], $results[1]['extensions']['requestId']['id']);
    }

    public function test_an_alias_flood_produces_a_single_error(): void
    {
        $query = '{ ' . implode(' ', array_map(fn(int $i): string => "a{$i}: ping", range(1, 50))) . ' }';

        $errors = $this->graphql($query)['errors'];

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('aliases', $errors[0]['message']);
    }
}
