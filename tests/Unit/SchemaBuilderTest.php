<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit;

use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use GraphQL\Type\Schema;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class TestUserType extends Type
{
    protected array $attributes = [
        'name'        => 'User',
        'description' => 'A user.',
    ];

    public function fields(): array
    {
        return [
            'id'   => ['type' => GType::nonNull(GType::id())],
            'name' => ['type' => GType::string()],
        ];
    }
}

class TestPingQuery extends Query
{
    public function type(): GType
    {
        return GType::string();
    }

    public function description(): ?string
    {
        return 'Returns pong.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

class TestEchoMutation extends Mutation
{
    public function type(): GType
    {
        return GType::string();
    }

    public function args(): array
    {
        return [
            'message' => ['type' => GType::nonNull(GType::string())],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $args['message'];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SchemaBuilderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.types', [
            'User' => TestUserType::class,
        ]);

        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['ping' => TestPingQuery::class],
            'mutation' => ['echo' => TestEchoMutation::class],
        ]);
    }

    public function test_schema_is_built_successfully(): void
    {
        /** @var Laragraph $laragraph */
        $laragraph = $this->app->make('laragraph');
        $schema    = $laragraph->schema();

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_schema_has_query_type(): void
    {
        $schema = $this->app->make('laragraph')->schema();

        $this->assertNotNull($schema->getQueryType());
        $this->assertArrayHasKey('ping', $schema->getQueryType()->getFields());
    }

    public function test_schema_has_mutation_type(): void
    {
        $schema = $this->app->make('laragraph')->schema();

        $this->assertNotNull($schema->getMutationType());
        $this->assertArrayHasKey('echo', $schema->getMutationType()->getFields());
    }

    public function test_type_registry_resolves_registered_type(): void
    {
        $laragraph = $this->app->make('laragraph');

        // Force schema build so types are registered
        $laragraph->schema();

        $this->assertTrue($laragraph->hasType('User'));
        $this->assertInstanceOf(TestUserType::class, $laragraph->type('User'));
    }

    public function test_unknown_schema_throws_schema_exception(): void
    {
        $this->expectException(SchemaException::class);

        $this->app->make('laragraph')->schema('nonexistent');
    }

    public function test_unknown_type_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->make('laragraph')->type('NonExistentType');
    }

    public function test_execute_ping_query(): void
    {
        $result = $this->app->make('laragraph')->execute('{ ping }');

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('pong', $result['data']['ping']);
    }

    public function test_execute_echo_mutation(): void
    {
        $result = $this->app->make('laragraph')->execute(
            'mutation($msg: String!) { echo(message: $msg) }',
            variables: ['msg' => 'hello laragraph'],
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertSame('hello laragraph', $result['data']['echo']);
    }

    public function test_schema_is_cached_on_repeated_calls(): void
    {
        $laragraph = $this->app->make('laragraph');
        $schema1   = $laragraph->schema();
        $schema2   = $laragraph->schema();

        $this->assertSame($schema1, $schema2);
    }
}
