<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\PHPStan;

use Ayimdomnic\Laragraph\PHPStan\KnownTypeNames;
use Ayimdomnic\Laragraph\PHPStan\UnregisteredTypeNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<UnregisteredTypeNameRule>
 */
class UnregisteredTypeNameRuleTest extends RuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        KnownTypeNames::reset();
    }

    protected function getRule(): Rule
    {
        return new UnregisteredTypeNameRule(__DIR__ . '/data');
    }

    public function test_registered_and_discovered_names_produce_no_errors(): void
    {
        $this->analyse([__DIR__ . '/data/CallSites.php'], [
            [
                'Type [Typo] is not registered in config/laragraph.php or discovered under app/GraphQL/Types. If this is a false positive because the type is only named through its runtime `->name` property, add a `NAME` constant to it instead.',
                29,
            ],
        ]);
    }
}
