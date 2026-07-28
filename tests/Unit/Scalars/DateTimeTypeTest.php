<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use Ayimdomnic\Laragraph\Scalars\DateTimeType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;

class DateTimeTypeTest extends TestCase
{
    use AstNodeFactory;

    private DateTimeType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new DateTimeType();
    }

    public function test_serialize_datetime_interface(): void
    {
        $dt     = new \DateTimeImmutable('2024-01-15T09:30:00+00:00');
        $result = $this->scalar->serialize($dt);
        $this->assertSame('2024-01-15T09:30:00+00:00', $result);
    }

    public function test_serialize_string_passthrough(): void
    {
        $result = $this->scalar->serialize('2024-01-15T09:30:00Z');
        $this->assertSame('2024-01-15T09:30:00Z', $result);
    }

    public function test_serialize_invalid_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(12345);
    }

    public function test_parse_value_string(): void
    {
        $result = $this->scalar->parseValue('2024-01-15T09:30:00+00:00');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function test_parse_value_datetime_immutable(): void
    {
        $dt     = new \DateTimeImmutable('2024-01-15');
        $result = $this->scalar->parseValue($dt);
        $this->assertSame($dt, $result);
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
        $this->scalar->parseValue('not-a-date');
    }

    public function test_parse_value_non_string_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(999);
    }

    public function test_parse_literal_string_node(): void
    {
        $result = $this->scalar->parseLiteral($this->strNode('2024-01-15T09:30:00+00:00'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function test_parse_literal_non_string_throws(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('123'));
    }
}
