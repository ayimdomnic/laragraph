<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PHPStan;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Shared logic behind {@see UnregisteredTypeNameRule} and
 * {@see UnregisteredTypeNameMethodCallRule}: given the arguments of a
 * `type($name)` call already confirmed to be on Laragraph, flag a literal
 * string argument that isn't in the project's known type name set.
 *
 * Skips silently — no error — for anything that isn't exactly one
 * string-literal argument: a variable, a concatenation, an interpolated
 * string. A rule that occasionally misses a typo is acceptable; one that
 * cries wolf on dynamic code is not.
 */
final class TypeNameCheck
{
    /**
     * @param array<int, Arg> $args
     * @param array<string, true> $knownNames
     * @return list<RuleError>
     */
    public static function check(array $args, Scope $scope, int $line, array $knownNames): array
    {
        if (count($args) < 1) {
            return [];
        }

        $argType = $scope->getType($args[0]->value);
        $strings = $argType->getConstantStrings();

        if (count($strings) !== 1) {
            return [];
        }

        $name = $strings[0]->getValue();

        if (isset($knownNames[$name])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Type [%s] is not registered in config/laragraph.php or discovered under app/GraphQL/Types. '
                    . 'If this is a false positive because the type is only named through its runtime `->name` '
                    . 'property, add a `NAME` constant to it instead.',
                $name,
            ))
                ->identifier('laragraph.unregisteredTypeName')
                ->line($line)
                ->build(),
        ];
    }
}
