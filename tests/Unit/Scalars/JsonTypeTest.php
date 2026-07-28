<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use Ayimdomnic\Laragraph\Scalars\JsonType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;

class JsonTypeTest extends TestCase
{
    use AstNodeFactory;

    private JsonType $scalar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scalar = new JsonType();
    }

    public function test_serialize_passthrough(): void
    {
        $this->assertSame(['a' => 1], $this->scalar->serialize(['a' => 1]));
        $this->assertSame(42, $this->scalar->serialize(42));
    }

    public function test_parse_value_passthrough(): void
    {
        $this->assertSame(['key' => 'val'], $this->scalar->parseValue(['key' => 'val']));
    }

    public function test_parse_literal_string_node(): void
    {
        $this->assertSame('hello', $this->scalar->parseLiteral($this->strNode('hello')));
    }

    public function test_parse_literal_int_node(): void
    {
        $this->assertSame(7, $this->scalar->parseLiteral($this->intNode('7')));
    }

    public function test_parse_literal_float_node(): void
    {
        $this->assertSame(3.14, $this->scalar->parseLiteral($this->floatNode('3.14')));
    }

    public function test_parse_literal_boolean_node(): void
    {
        $this->assertTrue($this->scalar->parseLiteral($this->boolNode(true)));
        $this->assertFalse($this->scalar->parseLiteral($this->boolNode(false)));
    }

    public function test_parse_literal_null_node(): void
    {
        $this->assertNull($this->scalar->parseLiteral($this->nullNode()));
    }

    public function test_parse_literal_list_node(): void
    {
        $node         = new ListValueNode([]);
        $node->values = new NodeList([$this->intNode('1'), $this->intNode('2')]);

        $result = $this->scalar->parseLiteral($node);
        $this->assertSame([1, 2], $result);
    }

    public function test_parse_literal_object_node(): void
    {
        $field        = new ObjectFieldNode([]);
        $field->name  = new \GraphQL\Language\AST\NameNode(['value' => 'key']);
        $field->value = $this->strNode('value');

        $node         = new ObjectValueNode([]);
        $node->fields = new NodeList([$field]);

        $result = $this->scalar->parseLiteral($node);
        $this->assertSame(['key' => 'value'], $result);
    }

    public function test_parse_literal_unknown_node_throws(): void
    {
        $this->expectException(Error::class);
        $unknown = new class extends \GraphQL\Language\AST\Node {
            public function __construct() { parent::__construct([]); }
            public function cloneDeep(): static { return clone $this; }
        };
        $this->scalar->parseLiteral($unknown);
    }
}
