<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\JsonbType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tests\Unit\Scalars\AstNodeFactory;

class JsonbTypeTest extends TestCase
{
    use AstNodeFactory;

    private JsonbType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new JsonbType();
    }

    public function test_name_is_jsonb(): void
    {
        $this->assertSame('JSONB', $this->scalar->name);
    }

    public function test_serialize_passthrough(): void
    {
        $this->assertSame(['a' => 1], $this->scalar->serialize(['a' => 1]));
        $this->assertSame(true, $this->scalar->serialize(true));
        $this->assertNull($this->scalar->serialize(null));
    }

    public function test_parse_value_passthrough(): void
    {
        $this->assertSame(['k' => 'v'], $this->scalar->parseValue(['k' => 'v']));
    }

    public function test_parse_literal_string(): void
    {
        $this->assertSame('hello', $this->scalar->parseLiteral($this->strNode('hello')));
    }

    public function test_parse_literal_int(): void
    {
        $this->assertSame(5, $this->scalar->parseLiteral($this->intNode('5')));
    }

    public function test_parse_literal_null(): void
    {
        $this->assertNull($this->scalar->parseLiteral($this->nullNode()));
    }
}
