<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\UuidType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;
use GraphQL\Error\Error;

class UuidTypeTest extends TestCase
{
    use AstNodeFactory;

    private UuidType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new UuidType();
    }

    // -------------------------------------------------------------------------
    // serialize()
    // -------------------------------------------------------------------------

    public function test_serialize_lowercases_uuid(): void
    {
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->scalar->serialize('550E8400-E29B-41D4-A716-446655440000'),
        );
    }

    public function test_serialize_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->serialize(42);
    }

    // -------------------------------------------------------------------------
    // parseValue()
    // -------------------------------------------------------------------------

    public function test_parse_value_valid_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertSame($uuid, $this->scalar->parseValue($uuid));
    }

    public function test_parse_value_normalises_to_lowercase(): void
    {
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $this->scalar->parseValue('550E8400-E29B-41D4-A716-446655440000'),
        );
    }

    public function test_parse_value_throws_for_invalid_uuid(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue('not-a-uuid');
    }

    public function test_parse_value_throws_for_non_string(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseValue(123);
    }

    // -------------------------------------------------------------------------
    // parseLiteral()
    // -------------------------------------------------------------------------

    public function test_parse_literal_string_node(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertSame($uuid, $this->scalar->parseLiteral($this->strNode($uuid)));
    }

    public function test_parse_literal_throws_for_non_string_node(): void
    {
        $this->expectException(Error::class);
        $this->scalar->parseLiteral($this->intNode('1'));
    }
}
