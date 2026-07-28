<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;

/**
 * Shared factory helpers for building lightweight GraphQL AST nodes in tests.
 */
trait AstNodeFactory
{
    protected function strNode(string $value): StringValueNode
    {
        return new StringValueNode(['value' => $value]);
    }

    protected function intNode(string $value): IntValueNode
    {
        return new IntValueNode(['value' => $value]);
    }

    protected function floatNode(string $value): FloatValueNode
    {
        return new FloatValueNode(['value' => $value]);
    }

    protected function boolNode(bool $value): BooleanValueNode
    {
        return new BooleanValueNode(['value' => $value]);
    }

    protected function nullNode(): NullValueNode
    {
        return new NullValueNode([]);
    }
}
