<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PHPStan;

use Ayimdomnic\Laragraph\Laragraph;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Type\ObjectType;

/**
 * Flags `app('laragraph')->type('SomeTypo')` — or any other call to
 * `->type()` on a resolved Laragraph manager instance — the same way
 * {@see UnregisteredTypeNameRule} flags the facade form.
 *
 * Only fires when PHPStan can actually resolve the call's target to
 * `Ayimdomnic\Laragraph\Laragraph` — e.g. `app('laragraph')` requires
 * Larastan's own container-binding return type inference. Without that,
 * this rule simply never matches rather than guessing, so it never produces
 * a false positive from a type it can't see.
 *
 * @implements Rule<MethodCall>
 */
final readonly class UnregisteredTypeNameMethodCallRule implements Rule
{
    public function __construct(private ?string $projectRoot = null) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->name !== 'type') {
            return [];
        }

        $calledOn = $scope->getType($node->var);

        if (!(new ObjectType(Laragraph::class))->isSuperTypeOf($calledOn)->yes()) {
            return [];
        }

        $knownNames = KnownTypeNames::resolve($this->projectRoot ?? (getcwd() ?: '.'));

        return TypeNameCheck::check($node->getArgs(), $scope, $node->getStartLine(), $knownNames);
    }
}
