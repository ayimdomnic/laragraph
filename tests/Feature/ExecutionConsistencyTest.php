<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Event;

class ConsistencyPingQuery extends Query
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

class ConsistencyErrorsHandler
{
    /**
     * @param array<int, Error>                   $errors
     * @param callable(Error): array<string, mixed> $formatter
     * @return array<int, array<string, mixed>>
     */
    public static function onlyFirst(array $errors, callable $formatter): array
    {
        return [['message' => 'handled: ' . count($errors) . ' error(s)'] + $formatter($errors[0])];
    }
}

class ExecutionConsistencyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', ['ping' => ConsistencyPingQuery::class]);
    }

    public function test_the_configured_errors_handler_is_used(): void
    {
        config(['laragraph.errors_handler' => [ConsistencyErrorsHandler::class, 'onlyFirst']]);

        $result = $this->graphql('{ nope1 nope2 }');

        $this->assertCount(1, $result['errors']);
        $this->assertSame('handled: 2 error(s)', $result['errors'][0]['message']);
    }

    public function test_cache_hits_fire_lifecycle_events_and_are_flagged(): void
    {
        config(['laragraph.cache.response.enabled' => true]);
        Event::fake([QueryExecuting::class, QueryExecuted::class]);

        $this->graphql('{ ping }');
        $this->graphql('{ ping }');

        Event::assertDispatchedTimes(QueryExecuting::class, 2);
        Event::assertDispatched(QueryExecuted::class, fn(QueryExecuted $event): bool => !$event->cached);
        Event::assertDispatched(QueryExecuted::class, fn(QueryExecuted $event): bool => $event->cached);
    }

    public function test_cache_hits_get_fresh_response_extensions(): void
    {
        config(['laragraph.cache.response.enabled' => true, 'laragraph.extensions.request_id' => true]);

        $first  = $this->postJson('/graphql', ['query' => '{ ping }'], ['X-Request-ID' => 'first'])->json();
        $second = $this->postJson('/graphql', ['query' => '{ ping }'], ['X-Request-ID' => 'second'])->json();

        $this->assertSame($first['data'], $second['data']);
        $this->assertSame('first', $first['extensions']['requestId']['id']);
        $this->assertSame('second', $second['extensions']['requestId']['id']);
    }
}
