<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\BigIntType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class BigIntTypeTest extends TestCase
{
    use AstNodeFactory;

    private BigIntType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new BigIntType();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_serialize_int(): void
    {
        $this->assertSame('42', $this->scalar->serialize(42));
    }

    public function test_serialize_large_int(): void
    {
        $this->assertSame('9007199254740993', $this->scalar->serialize(9007199254740993));
    }

    public function test_serialize_integer_string(): void
    {
        $this->assertSame('9007199254740993', $this->scalar->serialize('9007199254740993'));
    }

    public function test_serialize_negative_string(): void
    {
        $this->assertSame('-100', $this->scalar->serialize('-100'));
    }

    public function test_serialize_throws_for_float(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(3.14);
    }

    public function test_serialize_throws_for_non_numeric_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize('abc');
    }

    // -------------------------------------------------------------------------
    // parseValue()
    // -------------------------------------------------------------------------

    public function test_parse_value_int(): void
    {
        $this->assertSame('7', $this->scalar->parseValue(7));
    }

    public function test_parse_value_string(): void
    {
        $this->assertSame('-999', $this->scalar->parseValue('-999'));
    }

    public function test_parse_value_throws_for_float(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(1.5);
    }

    public function test_parse_value_throws_for_non_numeric(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('not-int');
    }

    // -------------------------------------------------------------------------
    // parseLiteral()
    // -------------------------------------------------------------------------

    public function test_parse_literal_int_node(): void
    {
        $this->assertSame('99', $this->scalar->parseLiteral($this->intNode('99')));
    }

    public function test_parse_literal_string_node(): void
    {
        $this->assertSame('9007199254740993', $this->scalar->parseLiteral($this->strNode('9007199254740993')));
    }

    public function test_parse_literal_throws_for_float_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->floatNode('1.5'));
    }
}
