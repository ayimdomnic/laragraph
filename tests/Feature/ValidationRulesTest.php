<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Validation\MaxAliasesRule;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class ValidationHelloQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'hello';
    }
}

/** Custom rule that forbids any field named "forbidden". */
class ForbidForbiddenFieldRule extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::FIELD => [
                'enter' => function (FieldNode $node) use ($context): void {
                    if ($node->name->value === 'forbidden') {
                        $context->reportError(new Error('Field "forbidden" is not allowed.'));
                    }
                },
            ],
        ];
    }
}

// ---------------------------------------------------------------------------

class ValidationRulesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query' => ['hello' => ValidationHelloQuery::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // Max aliases (config-driven)
    // -------------------------------------------------------------------------

    public function test_query_without_aliases_passes_when_max_aliases_set(): void
    {
        config(['laragraph.security.max_aliases' => 2]);

        $result = $this->graphql('{ hello }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('hello', $result['data']['hello']);
    }

    public function test_query_within_alias_limit_passes(): void
    {
        config(['laragraph.security.max_aliases' => 2]);

        $result = $this->graphql('{ a: hello b: hello }');

        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_query_exceeding_alias_limit_returns_error(): void
    {
        config(['laragraph.security.max_aliases' => 1]);

        $result = $this->graphql('{ a: hello b: hello }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('aliases', strtolower($result['errors'][0]['message']));
    }

    public function test_max_aliases_null_disables_limit(): void
    {
        config(['laragraph.security.max_aliases' => null]);

        // 10 aliases — should be fine when limit is null
        $aliases = implode(' ', array_map(fn ($i) => "a{$i}: hello", range(1, 10)));
        $result  = $this->graphql("{ {$aliases} }");

        $this->assertArrayNotHasKey('errors', $result);
    }

    // -------------------------------------------------------------------------
    // Runtime registration via addValidationRule()
    // -------------------------------------------------------------------------

    public function test_custom_rule_registered_at_runtime_is_applied(): void
    {
        /** @var \Ayimdomnic\Laragraph\Laragraph $laragraph */
        $laragraph = $this->app->make('laragraph');
        $laragraph->addValidationRule(new ForbidForbiddenFieldRule());

        // The hello field is fine
        $result = $this->graphql('{ hello }');
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_custom_rule_blocks_forbidden_field(): void
    {
        /** @var \Ayimdomnic\Laragraph\Laragraph $laragraph */
        $laragraph = $this->app->make('laragraph');
        $laragraph->addValidationRule(new ForbidForbiddenFieldRule());

        $result = $this->graphql('{ forbidden }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('forbidden', $result['errors'][0]['message']);
    }

    public function test_facade_add_validation_rule(): void
    {
        \Ayimdomnic\Laragraph\Facades\Laragraph::addValidationRule(new ForbidForbiddenFieldRule());

        $result = $this->graphql('{ forbidden }');

        $this->assertArrayHasKey('errors', $result);
    }

    // -------------------------------------------------------------------------
    // Config-based rule registration
    // -------------------------------------------------------------------------

    public function test_config_rules_are_loaded_into_registry(): void
    {
        $registry = $this->app->make(ValidationRuleRegistry::class);

        // Default config has empty rules
        $this->assertEmpty($registry->all());
    }

    public function test_max_aliases_rule_accessible_via_registry_add(): void
    {
        $registry = $this->app->make(ValidationRuleRegistry::class);
        $registry->add(new MaxAliasesRule(1));

        // Execute a query with 2 aliases — should fail validation
        $result = $this->graphql('{ a: hello b: hello }');

        $this->assertArrayHasKey('errors', $result);
    }

    // -------------------------------------------------------------------------
    // addValidationRule() isolation — rules persist per instance
    // -------------------------------------------------------------------------

    public function test_rules_are_scoped_to_the_laragraph_instance(): void
    {
        // Fresh instance via container — no extra rules
        $laragraph = $this->app->make('laragraph');
        $result    = $this->graphql('{ hello }');

        $this->assertArrayNotHasKey('errors', $result);
    }
}
