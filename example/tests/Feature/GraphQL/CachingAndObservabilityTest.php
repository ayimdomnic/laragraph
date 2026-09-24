<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Post;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class CachingAndObservabilityTest extends GraphQLTestCase
{
    public function test_repeated_queries_are_served_from_the_response_cache(): void
    {
        Post::factory()->published()->by($this->member)->create();
        $query = '{ posts { edges { node { title } } } }';

        $this->graphql($query, as: $this->member);

        DB::enableQueryLog();
        $this->graphql($query, as: $this->member)->assertJsonCount(1, 'data.posts.edges');

        $postQueries = array_filter(DB::getQueryLog(), fn (array $entry): bool => str_contains($entry['query'], '"posts"'));
        $this->assertSame([], $postQueries); // answered from the cache

        DB::disableQueryLog();
    }

    public function test_mutations_invalidate_the_response_cache(): void
    {
        $query = '{ posts { pageInfo { total } } }';
        $this->graphql($query, as: $this->member)->assertJsonPath('data.posts.pageInfo.total', 0);

        $this->graphql('mutation { createPost(input: { title: "Fresh", body: "Straight from the oven." }) { id } }', as: $this->member);

        $this->graphql($query, as: $this->member)->assertJsonPath('data.posts.pageInfo.total', 1);
    }

    public function test_cached_responses_are_never_shared_between_users(): void
    {
        $draft = Post::factory()->by($this->member)->create();
        $query = '{ posts { edges { node { id } } } }';

        $this->graphql($query, as: $this->member)->assertJsonCount(1, 'data.posts.edges');
        $this->graphql($query, as: $this->admin)->assertJsonCount(1, 'data.posts.edges');
        $this->graphql($query)->assertJsonCount(0, 'data.posts.edges'); // the guest never sees the draft
    }

    public function test_response_extensions(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ me { id } }'], ['X-Request-ID' => 'trace-123']);

        $response->assertJsonPath('extensions.requestId.id', 'trace-123')
            ->assertJsonPath('extensions.apiVersion.version', '2026-09')
            ->assertJsonStructure(['extensions' => ['timing' => ['execution_ms']]]);
    }

    public function test_tracing_reports_resolver_timings(): void
    {
        config(['laragraph.tracing.enabled' => true]);

        $resolvers = $this->graphql('{ organizations { edges { node { name } } } }')->json('extensions.tracing.execution.resolvers');

        $this->assertContains('organizations', array_column($resolvers, 'fieldName'));
    }

    public function test_lifecycle_events_drive_the_slow_query_log(): void
    {
        config(['app.graphql_slow_ms' => 0]);
        Log::spy();

        // The event carries the operationName the client sent.
        $this->postJson('/graphql', ['query' => 'query Dashboard { me { id } }', 'operationName' => 'Dashboard']);

        Log::shouldHaveReceived('warning')->with('Slow GraphQL operation', \Mockery::on(
            fn (array $context): bool => $context['operation'] === 'Dashboard' && $context['cached'] === false,
        ));
    }

    public function test_events_flag_cache_hits(): void
    {
        Event::fake([QueryExecuted::class]);

        $this->graphql('{ organizations { pageInfo { total } } }');
        $this->graphql('{ organizations { pageInfo { total } } }');

        Event::assertDispatched(QueryExecuted::class, fn (QueryExecuted $event): bool => $event->cached);
    }
}
