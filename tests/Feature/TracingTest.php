<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class TracingAuthorType extends Type
{
    protected array $attributes = ['name' => 'TracingAuthor'];

    public function fields(): array
    {
        return ['name' => GType::string()];
    }
}

class TracingBookType extends Type
{
    protected array $attributes = ['name' => 'TracingBook'];

    public function fields(): array
    {
        return [
            'title'  => GType::string(),
            'author' => app('laragraph')->type('TracingAuthor'),
        ];
    }

    protected function resolveAuthorField(mixed $root, array $args, mixed $context): mixed
    {
        return $root['author'];
    }
}

class TracingBooksQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('TracingBook'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return [
            ['title' => 'Book One', 'author' => ['name' => 'Author One']],
        ];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class TracingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.tracing.enabled', true);

        $app['config']->set('laragraph.types', [
            'TracingAuthor' => TracingAuthorType::class,
            'TracingBook'   => TracingBookType::class,
        ]);

        $app['config']->set('laragraph.schemas.default', [
            'query' => ['books' => TracingBooksQuery::class],
        ]);
    }

    public function test_response_includes_apollo_tracing_extension(): void
    {
        $result = $this->graphql('{ books { title author { name } } }');

        $this->assertArrayNotHasKey('errors', $result);
        $tracing = $result['extensions']['tracing'] ?? null;

        $this->assertNotNull($tracing);
        $this->assertSame(1, $tracing['version']);
        $this->assertIsString($tracing['startTime']);
        $this->assertIsString($tracing['endTime']);
        $this->assertIsInt($tracing['duration']);
        $this->assertGreaterThanOrEqual(0, $tracing['duration']);
    }

    public function test_tracing_records_a_resolver_span_for_every_field_including_nested_ones(): void
    {
        $result = $this->graphql('{ books { title author { name } } }');

        $resolvers = $result['extensions']['tracing']['execution']['resolvers'];
        $fieldPaths = array_map(fn (array $r) => implode('.', $r['path']), $resolvers);

        $this->assertContains('books', $fieldPaths);
        $this->assertContains('books.0.title', $fieldPaths);
        $this->assertContains('books.0.author', $fieldPaths);
        $this->assertContains('books.0.author.name', $fieldPaths);

        foreach ($resolvers as $resolver) {
            $this->assertArrayHasKey('parentType', $resolver);
            $this->assertArrayHasKey('fieldName', $resolver);
            $this->assertArrayHasKey('returnType', $resolver);
            $this->assertArrayHasKey('startOffset', $resolver);
            $this->assertGreaterThanOrEqual(0, $resolver['startOffset']);
            $this->assertGreaterThanOrEqual(0, $resolver['duration']);
        }
    }

    public function test_tracing_extension_is_absent_when_disabled(): void
    {
        config(['laragraph.tracing.enabled' => false]);

        $result = $this->graphql('{ books { title } }');

        $this->assertArrayNotHasKey('tracing', $result['extensions'] ?? []);
    }
}
