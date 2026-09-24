<?php

declare(strict_types=1);

namespace App\GraphQL\Validation;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

/**
 * CUSTOM VALIDATION RULE — rejects operations that ask for too many root
 * fields at once. Registered in config/laragraph.php under validation.rules;
 * runs on every document before execution.
 */
class MaxRootFieldsRule extends ValidationRule
{
    public function __construct(private readonly int $max = 10) {}

    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $operation) use ($context): void {
                $count = count($operation->selectionSet->selections);

                if ($count > $this->max) {
                    $context->reportError(new Error(
                        "An operation may select at most {$this->max} root fields; this one selects {$count}.",
                        [$operation],
                    ));
                }
            },
        ];
    }
}
