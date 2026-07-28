<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Validation;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Validation\MaxAliasesRule;
use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;

class MaxAliasesRuleTest extends TestCase
{
    private Schema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'hello' => ['type' => Type::string(), 'resolve' => fn () => 'world'],
                    'ping'  => ['type' => Type::string(), 'resolve' => fn () => 'pong'],
                ],
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Constructor / accessors
    // -------------------------------------------------------------------------

    public function test_stores_max_aliases(): void
    {
        $rule = new MaxAliasesRule(5);
        $this->assertSame(5, $rule->getMaxAliases());
    }

    // -------------------------------------------------------------------------
    // Validation — passes
    // -------------------------------------------------------------------------

    public function test_query_without_aliases_passes(): void
    {
        $rule   = new MaxAliasesRule(3);
        $doc    = Parser::parse('{ hello ping }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertEmpty($errors);
    }

    public function test_query_within_alias_limit_passes(): void
    {
        $rule   = new MaxAliasesRule(3);
        $doc    = Parser::parse('{ a: hello b: ping c: hello }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertEmpty($errors);
    }

    public function test_exactly_at_limit_passes(): void
    {
        $rule   = new MaxAliasesRule(2);
        $doc    = Parser::parse('{ a: hello b: ping }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertEmpty($errors);
    }

    // -------------------------------------------------------------------------
    // Validation — fails
    // -------------------------------------------------------------------------

    public function test_exceeding_alias_limit_fails(): void
    {
        $rule   = new MaxAliasesRule(2);
        $doc    = Parser::parse('{ a: hello b: ping c: hello }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertNotEmpty($errors);
        $this->assertInstanceOf(Error::class, $errors[0]);
        $this->assertStringContainsString('2', $errors[0]->getMessage());
        $this->assertStringContainsString('aliases', $errors[0]->getMessage());
    }

    public function test_zero_aliases_with_any_alias_fails(): void
    {
        $rule   = new MaxAliasesRule(0);
        $doc    = Parser::parse('{ a: hello }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertNotEmpty($errors);
    }

    public function test_error_message_contains_limit(): void
    {
        $rule   = new MaxAliasesRule(5);
        $doc    = Parser::parse('{ a: hello b: ping c: hello d: ping e: hello f: ping }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('5', $errors[0]->getMessage());
    }

    public function test_non_aliased_fields_not_counted(): void
    {
        // 1 alias + 5 plain fields — should pass with max_aliases = 1
        $rule   = new MaxAliasesRule(1);
        $doc    = Parser::parse('{ a: hello hello ping hello ping hello }');
        $errors = DocumentValidator::validate($this->schema, $doc, [$rule]);

        $this->assertEmpty($errors);
    }
}
