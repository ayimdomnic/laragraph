<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Performance;

use Ayimdomnic\Laragraph\Tests\Support\SyntheticSchema;

/**
 * Schema budgets: with PHP-FPM every request builds the schema from scratch,
 * so building it must cost nothing up front and a request must only build
 * the types it touches — however large the API grows.
 */
class SchemaBudgetTest extends PerformanceTestCase
{
    public function test_building_the_schema_constructs_no_types(): void
    {
        app('laragraph')->schema();

        $this->assertSame([], SyntheticSchema::$constructed);
    }

    /**
     * Reaching a type compiles its fields, which constructs (but does not
     * compile) the types those fields return: `thing7 { next { … } }` reaches
     * Thing7 and Thing8, and Thing8's `next` field names Thing9.
     */
    public function test_a_request_constructs_only_the_types_it_reaches(): void
    {
        $this->execute('{ thing7 { id f0 next { id f1 } } }');

        $this->assertSame(['Thing7', 'Thing8', 'Thing9'], SyntheticSchema::$constructed);
    }

    public function test_the_cost_of_a_request_does_not_grow_with_the_schema(): void
    {
        $this->execute('{ a: thing1 { id } b: thing150 { id } c: thing298 { id } }');

        // The three reached types, and the `next` type each one's fields name.
        $this->assertEqualsCanonicalizing(
            ['Thing1', 'Thing2', 'Thing150', 'Thing151', 'Thing298', 'Thing299'],
            SyntheticSchema::$constructed,
        );
    }

    public function test_introspection_is_the_only_operation_that_builds_everything(): void
    {
        config(['laragraph.security.disable_introspection' => false]);

        $this->execute('{ __schema { types { name } } }');

        $this->assertCount(self::SYNTHETIC_TYPES, array_unique(SyntheticSchema::$constructed));
    }
}
