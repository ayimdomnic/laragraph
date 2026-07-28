<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\MoneyType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class MoneyTypeTest extends TestCase
{
    use AstNodeFactory;

    private MoneyType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new MoneyType();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_serialize_integer_string(): void
    {
        $this->assertSame('1000', $this->scalar->serialize('1000'));
    }

    public function test_serialize_decimal_string(): void
    {
        $this->assertSame('12.50', $this->scalar->serialize('12.50'));
    }

    public function test_serialize_negative(): void
    {
        $this->assertSame('-9.99', $this->scalar->serialize('-9.99'));
    }

    public function test_serialize_int(): void
    {
        $this->assertSame('0', $this->scalar->serialize(0));
    }

    public function test_serialize_float(): void
    {
        $this->assertSame('12.5', $this->scalar->serialize(12.5));
    }

    public function test_serialize_throws_for_non_numeric(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize('abc');
    }

    // -------------------------------------------------------------------------
    // parseValue()
    // -------------------------------------------------------------------------

    public function test_parse_value_valid_decimal(): void
    {
        $this->assertSame('1234.56', $this->scalar->parseValue('1234.56'));
    }

    public function test_parse_value_zero(): void
    {
        $this->assertSame('0', $this->scalar->parseValue('0'));
    }

    public function test_parse_value_throws_for_invalid(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('1,000.00');
    }

    public function test_parse_value_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue([]);
    }

    // -------------------------------------------------------------------------
    // parseLiteral()
    // -------------------------------------------------------------------------

    public function test_parse_literal_string_node(): void
    {
        $this->assertSame('99.95', $this->scalar->parseLiteral($this->strNode('99.95')));
    }

    public function test_parse_literal_int_node(): void
    {
        $this->assertSame('50', $this->scalar->parseLiteral($this->intNode('50')));
    }

    public function test_parse_literal_float_node(): void
    {
        $this->assertSame('3.14', $this->scalar->parseLiteral($this->floatNode('3.14')));
    }

    public function test_parse_literal_throws_for_bool_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->boolNode(true));
    }
}
