<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\IntervalType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class IntervalTypeTest extends TestCase
{
    use AstNodeFactory;

    private IntervalType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new IntervalType();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_serialize_string_passthrough(): void
    {
        $this->assertSame('P1Y2M3D', $this->scalar->serialize('P1Y2M3D'));
    }

    public function test_serialize_date_interval_with_time(): void
    {
        $interval = new \DateInterval('P1Y2M3DT4H5M6S');
        $this->assertSame('P1Y2M3DT4H5M6S', $this->scalar->serialize($interval));
    }

    public function test_serialize_date_interval_date_only(): void
    {
        $interval = new \DateInterval('P5D');
        $this->assertSame('P5D', $this->scalar->serialize($interval));
    }

    public function test_serialize_date_interval_zero_returns_p0d(): void
    {
        $interval = new \DateInterval('P0D');
        $this->assertSame('P0D', $this->scalar->serialize($interval));
    }

    public function test_serialize_date_interval_time_only(): void
    {
        $interval = new \DateInterval('PT30M');
        $this->assertSame('PT30M', $this->scalar->serialize($interval));
    }

    public function test_serialize_throws_for_non_string_non_interval(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(42);
    }

    // -------------------------------------------------------------------------
    // parseValue()
    // -------------------------------------------------------------------------

    public function test_parse_value_iso_string(): void
    {
        $this->assertSame('PT1H30M', $this->scalar->parseValue('PT1H30M'));
    }

    public function test_parse_value_postgres_style(): void
    {
        $this->assertSame('1 year 2 months', $this->scalar->parseValue('1 year 2 months'));
    }

    public function test_parse_value_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(100);
    }

    // -------------------------------------------------------------------------
    // parseLiteral()
    // -------------------------------------------------------------------------

    public function test_parse_literal_string_node(): void
    {
        $this->assertSame('P1D', $this->scalar->parseLiteral($this->strNode('P1D')));
    }

    public function test_parse_literal_throws_for_non_string_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('1'));
    }
}
