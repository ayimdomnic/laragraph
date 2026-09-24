<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;

class ForbiddenFieldRule extends ValidationRule
{
    public function __construct(private readonly string $field) {}

    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::FIELD => function (FieldNode $node) use ($context): void {
                if ($node->name->value === $this->field) {
                    $context->reportError(new Error("Field {$this->field} is forbidden."));
                }
            },
        ];
    }
}

class RuleInstancesQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'x';
    }
}

class CustomValidationRuleInstancesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', [
            'alpha' => RuleInstancesQuery::class,
            'beta'  => RuleInstancesQuery::class,
        ]);
    }

    public function test_several_instances_of_one_rule_class_all_apply(): void
    {
        Laragraph::addValidationRule(new ForbiddenFieldRule('alpha'));
        Laragraph::addValidationRule(new ForbiddenFieldRule('beta'));

        $messages = array_column($this->graphql('{ alpha beta }')['errors'] ?? [], 'message');

        $this->assertContains('Field alpha is forbidden.', $messages);
        $this->assertContains('Field beta is forbidden.', $messages);
    }
}
