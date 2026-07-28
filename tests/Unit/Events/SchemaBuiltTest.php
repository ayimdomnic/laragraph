<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Events;

use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Schema;

class SchemaBuiltTest extends TestCase
{
    private function makeSchema(): Schema
    {
        return \Mockery::mock(Schema::class);
    }

    public function test_stores_schema_name(): void
    {
        $event = new SchemaBuilt('admin', $this->makeSchema());

        $this->assertSame('admin', $event->schemaName);
    }

    public function test_stores_schema_instance(): void
    {
        $schema = $this->makeSchema();
        $event  = new SchemaBuilt('default', $schema);

        $this->assertSame($schema, $event->schema);
    }

    public function test_accepts_any_schema_name_string(): void
    {
        $event = new SchemaBuilt('my-custom-schema', $this->makeSchema());

        $this->assertSame('my-custom-schema', $event->schemaName);
    }
}
