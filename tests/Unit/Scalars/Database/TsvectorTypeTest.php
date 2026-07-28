<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\TsvectorType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class TsvectorTypeTest extends TestCase
{
    use AstNodeFactory;

    private TsvectorType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new TsvectorType();
    }

    public function test_serialize_string(): void
    {
        $this->assertSame("'cat':1 'fat':2", $this->scalar->serialize("'cat':1 'fat':2"));
    }

    public function test_serialize_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(42);
    }

    public function test_parse_value_string(): void
    {
        $this->assertSame("'sat':3", $this->scalar->parseValue("'sat':3"));
    }

    public function test_parse_value_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(true);
    }

    public function test_parse_literal_string_node(): void
    {
        $this->assertSame("'word':1", $this->scalar->parseLiteral($this->strNode("'word':1")));
    }

    public function test_parse_literal_throws_for_non_string_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('1'));
    }
}
