<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Laragraph as LaragraphManager;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;

class VcCheapQuery extends Query
{
    public function type(): GType
    {
        return GType::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'cheap';
    }
}

class VcExpensiveQuery extends VcCheapQuery
{
    public function complexity(): ?int
    {
        return 50;
    }
}

class VcCountingRule extends ValidationRule
{
    public static int $runs = 0;

    public function getVisitor(QueryValidationContext $context): array
    {
        self::$runs++;

        return [];
    }
}

class ValidationCacheTest extends TestCase
{
    public $validated;
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', ['query' => [
            'cheap'     => VcCheapQuery::class,
            'second'    => VcCheapQuery::class,
            'expensive' => VcExpensiveQuery::class,
        ]]);
        $app['config']->set('laragraph.schemas.other', ['query' => ['cheap' => VcCheapQuery::class]]);
        $app['config']->set('laragraph.security.query_max_complexity', 20);
    }

    private function validatedCount(): int
    {
        return count((fn() => $this->validated)->call(app('laragraph')));
    }

    public function test_a_valid_document_is_validated_once_per_worker(): void
    {
        $this->assertSame(['cheap' => 'cheap'], Laragraph::execute('{ cheap }')['data']);
        $this->assertSame(1, $this->validatedCount());

        $this->assertSame(['cheap' => 'cheap'], Laragraph::execute('{ cheap }')['data']);
        $this->assertSame(1, $this->validatedCount());
    }

    public function test_the_validation_cache_is_bounded(): void
    {
        for ($i = 0; $i < LaragraphManager::VALIDATED_DOCUMENTS + 10; $i++) {
            Laragraph::execute("{ cheap alias{$i}: second }");
        }

        $this->assertSame(LaragraphManager::VALIDATED_DOCUMENTS, $this->validatedCount());
    }

    public function test_invalid_documents_are_rejected_every_time(): void
    {
        foreach ([1, 2] as $attempt) {
            $result = Laragraph::execute('{ cheap nope }');

            $this->assertArrayNotHasKey('data', $result, "attempt {$attempt}");
            $this->assertStringContainsString('Cannot query field "nope"', $result['errors'][0]['message']);
        }

        $this->assertSame(0, $this->validatedCount());
    }

    public function test_a_stricter_limit_revalidates_documents_that_passed_before(): void
    {
        $query = '{ __schema { queryType { fields { type { ofType { name } } } } } }';

        config(['laragraph.security.query_max_depth' => 10]);
        $this->assertArrayNotHasKey('errors', Laragraph::execute($query));

        config(['laragraph.security.query_max_depth' => 2]);
        $this->assertStringContainsString('Max query depth should be 2', Laragraph::execute($query)['errors'][0]['message']);
    }

    public function test_turning_introspection_off_applies_to_documents_validated_while_it_was_on(): void
    {
        $query = '{ __schema { queryType { name } } }';

        config(['laragraph.security.disable_introspection' => false]);
        $this->assertSame('Query', Laragraph::execute($query)['data']['__schema']['queryType']['name']);

        config(['laragraph.security.disable_introspection' => true]);
        $this->assertStringContainsString('introspection', strtolower(Laragraph::execute($query)['errors'][0]['message']));
    }

    public function test_query_complexity_is_checked_on_every_execution_with_its_variables(): void
    {
        $query = 'query ($all: Boolean!) { cheap expensive @include(if: $all) }';

        $this->assertArrayNotHasKey('errors', Laragraph::execute($query, variables: ['all' => false]));

        $result = Laragraph::execute($query, variables: ['all' => true]);
        $this->assertStringContainsString('Max query complexity should be 20', $result['errors'][0]['message']);
    }

    public function test_application_rules_run_on_every_execution(): void
    {
        VcCountingRule::$runs = 0;
        Laragraph::addValidationRule(VcCountingRule::class);

        Laragraph::execute('{ cheap }');
        Laragraph::execute('{ cheap }');

        $this->assertSame(2, VcCountingRule::$runs);
    }

    public function test_a_document_valid_on_one_schema_is_still_validated_on_another(): void
    {
        $this->assertArrayNotHasKey('errors', Laragraph::execute('{ cheap second }'));

        $result = Laragraph::execute('{ cheap second }', schemaName: 'other');
        $this->assertStringContainsString('Cannot query field "second"', $result['errors'][0]['message']);
    }

    public function test_syntax_errors_are_still_reported(): void
    {
        $result = Laragraph::execute('{ cheap');

        $this->assertStringContainsString('Syntax Error', $result['errors'][0]['message']);
    }
}
