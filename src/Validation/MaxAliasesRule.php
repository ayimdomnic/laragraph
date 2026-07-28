<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Validation;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

/**
 * GraphQL validation rule: limit the total number of field aliases per query.
 *
 * Alias flooding is a known GraphQL attack vector in which a client sends a
 * single query with hundreds of aliases for the same expensive field, bypassing
 * query-complexity limits (which only count unique field names).
 *
 * Example malicious query that this rule blocks when max_aliases is set:
 *
 *   {
 *     a1: expensiveField
 *     a2: expensiveField
 *     a3: expensiveField
 *     ...
 *   }
 *
 * Enable via config:
 *
 *   'security' => [
 *       'max_aliases' => 5,
 *   ],
 *
 * Or register directly:
 *
 *   Laragraph::addValidationRule(new MaxAliasesRule(5));
 */
class MaxAliasesRule extends ValidationRule
{
    public function __construct(protected readonly int $maxAliases) {}

    public function getVisitor(ValidationContext $context): array
    {
        $count = 0;

        return [
            NodeKind::FIELD => [
                'enter' => function (FieldNode $node) use ($context, &$count): void {
                    if ($node->alias === null) {
                        return;
                    }

                    $count++;

                    if ($count > $this->maxAliases) {
                        $context->reportError(new Error(
                            "Exceeded maximum number of aliases ({$this->maxAliases}) allowed per query.",
                        ));
                    }
                },
            ],
        ];
    }

    public function getMaxAliases(): int
    {
        return $this->maxAliases;
    }
}
