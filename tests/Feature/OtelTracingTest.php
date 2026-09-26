<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class OtelAuthorType extends Type
{
    protected array $attributes = ['name' => 'OtelAuthor'];

    public function fields(): array
    {
        return ['name' => GType::string()];
    }
}

class OtelBooksQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('OtelAuthor'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return [['name' => 'Author One']];
    }
}

class OtelFailingQuery extends Query
{
    public function type(): GType
    {
        return GType::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        throw new \RuntimeException('boom');
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class OtelTracingTest extends TestCase
{
    private InMemoryExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        Globals::reset();
        $this->exporter = new InMemoryExporter();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->exporter));

        Globals::registerInitializer(
            fn(Configurator $c): Configurator => $c->withTracerProvider($tracerProvider),
        );
    }

    protected function tearDown(): void
    {
        Globals::reset();
        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.tracing.enabled', true);
        $app['config']->set('laragraph.tracing.driver', 'otel');

        $app['config']->set('laragraph.types', ['OtelAuthor' => OtelAuthorType::class]);
        $app['config']->set('laragraph.schemas.default', [
            'query' => ['books' => OtelBooksQuery::class, 'failing' => OtelFailingQuery::class],
        ]);
    }

    public function test_otel_driver_does_not_add_the_apollo_tracing_extension(): void
    {
        $result = $this->graphql('{ books { name } }');

        $this->assertArrayNotHasKey('tracing', $result['extensions'] ?? []);
    }

    public function test_otel_driver_exports_a_root_span_and_one_child_per_resolver(): void
    {
        $this->graphql('{ books { name } }');

        $spans = $this->exporter->getSpans();
        $names = array_map(fn($span) => $span->getName(), $spans);

        $this->assertContains('GraphQL Operation', $names);
        $this->assertContains('books', $names);
        $this->assertContains('name', $names);
    }

    public function test_root_span_carries_graphql_attributes(): void
    {
        $this->postJson('/graphql', ['query' => 'query Books { books { name } }', 'operationName' => 'Books']);

        $root = $this->rootSpan();

        $this->assertSame('query', $root->getAttributes()->get('graphql.operation.type'));
        $this->assertSame('Books', $root->getAttributes()->get('graphql.operation.name'));
        $this->assertStringContainsString('books', (string) $root->getAttributes()->get('graphql.document'));
        $this->assertSame('default', $root->getAttributes()->get('laragraph.schema'));
    }

    public function test_child_spans_are_nested_under_the_root_span(): void
    {
        $this->graphql('{ books { name } }');

        $root = $this->rootSpan();
        $child = $this->findSpan('books');

        $this->assertSame($root->getSpanId(), $child->getParentSpanId());
        $this->assertSame($root->getTraceId(), $child->getTraceId());
    }

    public function test_root_span_status_is_error_when_the_query_has_errors(): void
    {
        $this->graphql('{ failing }');

        $root = $this->rootSpan();

        $this->assertSame(StatusCode::STATUS_ERROR, $root->getStatus()->getCode());
    }

    public function test_root_span_status_is_unset_without_errors(): void
    {
        $this->graphql('{ books { name } }');

        $root = $this->rootSpan();

        $this->assertSame(StatusCode::STATUS_UNSET, $root->getStatus()->getCode());
    }

    public function test_nothing_is_exported_when_tracing_is_disabled(): void
    {
        config(['laragraph.tracing.enabled' => false]);

        $this->graphql('{ books { name } }');

        $this->assertSame([], $this->exporter->getSpans());
    }

    private function rootSpan()
    {
        foreach ($this->exporter->getSpans() as $span) {
            if (!$span->getParentContext()->isValid()) {
                return $span;
            }
        }

        $this->fail('No root span was exported.');
    }

    private function findSpan(string $name)
    {
        foreach ($this->exporter->getSpans() as $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }

        $this->fail("No exported span named [{$name}].");
    }
}
