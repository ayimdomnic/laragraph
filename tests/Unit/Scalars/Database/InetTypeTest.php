<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\InetType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class InetTypeTest extends TestCase
{
    use AstNodeFactory;

    private InetType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new InetType();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_serialize_ipv4(): void
    {
        $this->assertSame('192.168.1.1', $this->scalar->serialize('192.168.1.1'));
    }

    public function test_serialize_ipv6(): void
    {
        $this->assertSame('::1', $this->scalar->serialize('::1'));
    }

    public function test_serialize_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(42);
    }

    // -------------------------------------------------------------------------
    // parseValue()
    // -------------------------------------------------------------------------

    public function test_parse_value_ipv4(): void
    {
        $this->assertSame('10.0.0.1', $this->scalar->parseValue('10.0.0.1'));
    }

    public function test_parse_value_ipv4_cidr(): void
    {
        $this->assertSame('192.168.0.0/24', $this->scalar->parseValue('192.168.0.0/24'));
    }

    public function test_parse_value_ipv6(): void
    {
        $this->assertSame('2001:db8::1', $this->scalar->parseValue('2001:db8::1'));
    }

    public function test_parse_value_ipv6_cidr(): void
    {
        $this->assertSame('2001:db8::/32', $this->scalar->parseValue('2001:db8::/32'));
    }

    public function test_parse_value_throws_for_invalid_ip(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('999.999.999.999');
    }

    public function test_parse_value_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(false);
    }

    // -------------------------------------------------------------------------
    // parseLiteral()
    // -------------------------------------------------------------------------

    public function test_parse_literal_ipv4(): void
    {
        $this->assertSame('127.0.0.1', $this->scalar->parseLiteral($this->strNode('127.0.0.1')));
    }

    public function test_parse_literal_throws_for_non_string_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('1'));
    }
}
