<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Events\QueryError;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class EventPingQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'pong'; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class LaragraphEventsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laragraph.schemas.default', [
            'query' => ['eventPing' => EventPingQuery::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // SchemaBuilt
    // -------------------------------------------------------------------------

    public function test_schema_built_event_fires_on_first_schema_build(): void
    {
        Event::fake([SchemaBuilt::class]);

        $this->graphql('{ eventPing }');

        Event::assertDispatched(SchemaBuilt::class, fn ($e) => $e->schemaName === 'default');
    }

    public function test_schema_built_event_fires_only_once_for_the_same_schema(): void
    {
        Event::fake([SchemaBuilt::class]);

        /** @var Laragraph $laragraph */
        $laragraph = $this->app->make(Laragraph::class);
        $laragraph->schema('default'); // first compile → event fires
        $laragraph->schema('default'); // cached — no event

        Event::assertDispatchedTimes(SchemaBuilt::class, 1);
    }

    // -------------------------------------------------------------------------
    // QueryExecuting
    // -------------------------------------------------------------------------

    public function test_query_executing_event_fires_with_correct_data(): void
    {
        Event::fake([QueryExecuting::class]);

        $this->graphql('{ eventPing }');

        Event::assertDispatched(QueryExecuting::class, function ($e) {
            return $e->query       === '{ eventPing }'
                && $e->schemaName  === 'default'
                && $e->variables   === []
                && $e->operationName === null;
        });
    }

    public function test_query_executing_includes_variables(): void
    {
        Event::fake([QueryExecuting::class]);

        $this->postJson('/graphql', [
            'query'     => 'query GetPing($v: String) { eventPing }',
            'variables' => ['v' => 'test'],
            'operationName' => 'GetPing',
        ]);

        Event::assertDispatched(QueryExecuting::class, function ($e) {
            return $e->variables     === ['v' => 'test']
                && $e->operationName === 'GetPing';
        });
    }

    // -------------------------------------------------------------------------
    // QueryExecuted
    // -------------------------------------------------------------------------

    public function test_query_executed_event_fires_after_successful_query(): void
    {
        Event::fake([QueryExecuted::class]);

        $this->graphql('{ eventPing }');

        Event::assertDispatched(QueryExecuted::class, function ($e) {
            return $e->schemaName  === 'default'
                && $e->hasErrors   === false
                && ($e->result['data']['eventPing'] ?? null) === 'pong'
                && $e->executionMs >= 0.0;
        });
    }

    public function test_query_executed_has_errors_true_when_query_fails(): void
    {
        Event::fake([QueryExecuted::class]);

        $this->graphql('{ nonExistentField }');

        Event::assertDispatched(QueryExecuted::class, fn ($e) => $e->hasErrors === true);
    }

    // -------------------------------------------------------------------------
    // QueryError
    // -------------------------------------------------------------------------

    public function test_query_error_event_fires_when_response_contains_errors(): void
    {
        Event::fake([QueryError::class]);

        $this->graphql('{ nonExistentField }');

        Event::assertDispatched(QueryError::class, function ($e) {
            return $e->schemaName === 'default'
                && !empty($e->errors);
        });
    }

    public function test_query_error_event_not_fired_on_successful_query(): void
    {
        Event::fake([QueryError::class]);

        $this->graphql('{ eventPing }');

        Event::assertNotDispatched(QueryError::class);
    }

    public function test_query_error_errors_array_has_message(): void
    {
        Event::fake([QueryError::class]);

        $this->graphql('{ nonExistentField }');

        Event::assertDispatched(QueryError::class, function ($e) {
            return isset($e->errors[0]['message']);
        });
    }
}
