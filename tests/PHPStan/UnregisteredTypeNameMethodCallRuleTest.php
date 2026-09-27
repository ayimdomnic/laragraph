<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\PHPStan;

use Ayimdomnic\Laragraph\PHPStan\KnownTypeNames;
use Ayimdomnic\Laragraph\PHPStan\UnregisteredTypeNameMethodCallRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<UnregisteredTypeNameMethodCallRule>
 */
class UnregisteredTypeNameMethodCallRuleTest extends RuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        KnownTypeNames::reset();
    }

    protected function getRule(): Rule
    {
        return new UnregisteredTypeNameMethodCallRule(__DIR__ . '/data');
    }

    public function test_registered_names_produce_no_errors_and_unregistered_ones_are_flagged(): void
    {
        $this->analyse([__DIR__ . '/data/CallSites.php'], [
            [
                'Type [Typo] is not registered in config/laragraph.php or discovered under app/GraphQL/Types. If this is a false positive because the type is only named through its runtime `->name` property, add a `NAME` constant to it instead.',
                47,
            ],
        ]);
    }
}
