<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Extensions;

use Ayimdomnic\Laragraph\Extensions\QueryComplexityExtension;
use Ayimdomnic\Laragraph\Extensions\QueryComplexityState;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Validator\Rules\QueryComplexity;

class QueryComplexityExtensionTest extends TestCase
{
    public function test_key_is_query_complexity(): void
    {
        $ext = new QueryComplexityExtension(new QueryComplexityState());

        $this->assertSame('queryComplexity', $ext->key());
    }

    public function test_returns_empty_array_when_no_rule_ran(): void
    {
        $ext = new QueryComplexityExtension(new QueryComplexityState());

        $this->assertSame([], $ext->get());
    }

    public function test_reads_the_current_rule_from_the_state(): void
    {
        $rule = \Mockery::mock(QueryComplexity::class);
        $rule->shouldReceive('getQueryComplexity')->andReturn(42);
        $rule->shouldReceive('getMaxQueryComplexity')->andReturn(500);

        $state = new QueryComplexityState();
        $state->setCurrent($rule);

        $data = (new QueryComplexityExtension($state))->get();

        $this->assertSame(500, $data['maxCost']);
        $this->assertSame(42, $data['cost']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
