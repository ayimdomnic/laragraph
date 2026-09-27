<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Extensions;

use Ayimdomnic\Laragraph\Extensions\QueryComplexityState;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Validator\Rules\QueryComplexity;

class QueryComplexityStateTest extends TestCase
{
    public function test_is_initially_null(): void
    {
        $state = new QueryComplexityState();

        $this->assertNull($state->current());
    }

    public function test_set_current_and_read_it_back(): void
    {
        $state = new QueryComplexityState();
        $rule  = new QueryComplexity(500);

        $state->setCurrent($rule);

        $this->assertSame($rule, $state->current());
    }

    public function test_set_current_to_null_clears_it(): void
    {
        $state = new QueryComplexityState();
        $state->setCurrent(new QueryComplexity(500));

        $state->setCurrent(null);

        $this->assertNull($state->current());
    }
}
