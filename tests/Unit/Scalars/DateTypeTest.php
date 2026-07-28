<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use Ayimdomnic\Laragraph\Scalars\DateType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;

class DateTypeTest extends TestCase
{
    use AstNodeFactory;

    private DateType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new DateType();
    }

    public function test_serialize_datetime_interface(): void
    {
        $dt = new \DateTimeImmutable('2024-01-15');
        $this->assertSame('2024-01-15', $this->scalar->serialize($dt));
    }

    public function test_serialize_valid_string(): void
    {
        $this->assertSame('2024-01-15', $this->scalar->serialize('2024-01-15'));
    }

    public function test_serialize_invalid_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize('not-a-date');
    }

    public function test_parse_value_string(): void
    {
        $result = $this->scalar->parseValue('2024-01-15');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function test_parse_value_datetime_immutable(): void
    {
        $dt = new \DateTimeImmutable('2024-01-15');
        $this->assertSame($dt, $this->scalar->parseValue($dt));
    }

    public function test_parse_value_datetime(): void
    {
        $dt     = new \DateTime('2024-01-15');
        $result = $this->scalar->parseValue($dt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function test_parse_value_invalid_string_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('notadate');
    }

    public function test_parse_value_non_string_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(true);
    }

    public function test_parse_literal_string_node(): void
    {
        $result = $this->scalar->parseLiteral($this->strNode('2024-06-01'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function test_parse_literal_non_string_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('123'));
    }
}
