<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Schema;

use Ayimdomnic\Laragraph\Contracts\SchemaBuilderInterface;
use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Schema\SchemaRegistry;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Schema;

class SchemaRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_throws_when_the_schema_is_not_configured(): void
    {
        $registry = new SchemaRegistry(\Mockery::mock(SchemaBuilderInterface::class));

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Schema [missing] not found');

        $registry->schema('missing');
    }

    public function test_builds_and_caches_a_configured_schema(): void
    {
        config(['laragraph.schemas.default' => ['query' => []]]);

        $schema  = new Schema(['query' => null]);
        $builder = \Mockery::mock(SchemaBuilderInterface::class);
        $builder->shouldReceive('build')->once()->andReturn($schema);

        $registry = new SchemaRegistry($builder);

        $first  = $registry->schema('default');
        $second = $registry->schema('default');

        $this->assertSame($schema, $first);
        $this->assertSame($first, $second);
    }

    public function test_merges_global_types_into_the_schema_config(): void
    {
        config([
            'laragraph.types'          => ['Global' => 'App\\Global'],
            'laragraph.schemas.default' => ['query' => [], 'types' => ['Local' => 'App\\Local']],
        ]);

        $builder = \Mockery::mock(SchemaBuilderInterface::class);
        $builder->shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn(array $config): bool => $config['types'] === ['Global' => 'App\\Global', 'Local' => 'App\\Local']))
            ->andReturn(new Schema(['query' => null]));

        (new SchemaRegistry($builder))->schema('default');
        $this->addToAssertionCount(1);
    }

    public function test_defaults_to_the_configured_default_schema_name(): void
    {
        config(['laragraph.default_schema' => 'admin', 'laragraph.schemas.admin' => ['query' => []]]);

        $builder = \Mockery::mock(SchemaBuilderInterface::class);
        $builder->shouldReceive('build')->once()->andReturn(new Schema(['query' => null]));

        (new SchemaRegistry($builder))->schema();
        $this->addToAssertionCount(1);
    }
}
