<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PHPStan;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Type\ObjectType;

/**
 * Flags `Laragraph::type('SomeTypo')` — a static call through the facade —
 * when the literal type name isn't in the project's best-effort known-name
 * set. See {@see KnownTypeNames} for exactly what that set covers, and its
 * documented blind spot.
 *
 * @implements Rule<StaticCall>
 */
final readonly class UnregisteredTypeNameRule implements Rule
{
    public function __construct(private ?string $projectRoot = null) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->name !== 'type') {
            return [];
        }

        if (!$this->isLaragraphFacadeCall($node, $scope)) {
            return [];
        }

        $knownNames = KnownTypeNames::resolve($this->projectRoot ?? (getcwd() ?: '.'));

        return TypeNameCheck::check($node->getArgs(), $scope, $node->getStartLine(), $knownNames);
    }

    private function isLaragraphFacadeCall(StaticCall $node, Scope $scope): bool
    {
        $class = $node->class;

        $type = $class instanceof Name
            ? new ObjectType($scope->resolveName($class))
            : $scope->getType($class);

        return (new ObjectType(Laragraph::class))->isSuperTypeOf($type)->yes();
    }
}
