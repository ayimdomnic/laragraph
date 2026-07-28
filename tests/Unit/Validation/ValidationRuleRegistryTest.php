<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Validation;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use GraphQL\Validator\Rules\ValidationRule;
use Mockery;

class ValidationRuleRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_is_initially_empty(): void
    {
        $registry = new ValidationRuleRegistry();
        $this->assertTrue($registry->isEmpty());
        $this->assertSame([], $registry->all());
    }

    public function test_add_instance(): void
    {
        $registry = new ValidationRuleRegistry();
        $rule     = Mockery::mock(ValidationRule::class);

        $registry->add($rule);

        $this->assertFalse($registry->isEmpty());
        $this->assertCount(1, $registry->all());
        $this->assertSame($rule, $registry->all()[0]);
    }

    public function test_add_fqcn_string(): void
    {
        $registry = new ValidationRuleRegistry();

        $registry->add(\GraphQL\Validator\Rules\QueryDepth::class);

        $this->assertCount(1, $registry->all());
        $this->assertSame(\GraphQL\Validator\Rules\QueryDepth::class, $registry->all()[0]);
    }

    public function test_add_multiple_rules(): void
    {
        $registry = new ValidationRuleRegistry();
        $ruleA    = Mockery::mock(ValidationRule::class);
        $ruleB    = Mockery::mock(ValidationRule::class);

        $registry->add($ruleA);
        $registry->add($ruleB);

        $this->assertCount(2, $registry->all());
    }

    public function test_resolve_returns_instances(): void
    {
        $registry = new ValidationRuleRegistry();
        $rule     = Mockery::mock(ValidationRule::class);

        $registry->add($rule);
        $resolved = $registry->resolve();

        $this->assertCount(1, $resolved);
        $this->assertSame($rule, $resolved[0]);
    }

    public function test_resolve_instantiates_fqcn_via_container(): void
    {
        $registry = new ValidationRuleRegistry();

        // QueryDepth can be constructed by the container (no constructor args issue
        // since we just need it resolvable in the test app)
        $this->app->bind(\GraphQL\Validator\Rules\QueryDepth::class, fn () => new \GraphQL\Validator\Rules\QueryDepth(10));
        $registry->add(\GraphQL\Validator\Rules\QueryDepth::class);

        $resolved = $registry->resolve();

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(\GraphQL\Validator\Rules\QueryDepth::class, $resolved[0]);
    }

    public function test_resolve_returns_zero_indexed_array(): void
    {
        $registry = new ValidationRuleRegistry();
        $ruleA    = Mockery::mock(ValidationRule::class);
        $ruleB    = Mockery::mock(ValidationRule::class);

        $registry->add($ruleA);
        $registry->add($ruleB);

        $resolved = $registry->resolve();

        $this->assertSame([0, 1], array_keys($resolved));
    }

    public function test_isEmpty_returns_false_after_adding(): void
    {
        $registry = new ValidationRuleRegistry();
        $this->assertTrue($registry->isEmpty());

        $registry->add(Mockery::mock(ValidationRule::class));

        $this->assertFalse($registry->isEmpty());
    }

    public function test_service_provider_binds_singleton(): void
    {
        $a = $this->app->make(ValidationRuleRegistry::class);
        $b = $this->app->make(ValidationRuleRegistry::class);

        $this->assertSame($a, $b);
    }

    public function test_service_provider_loads_rules_from_config(): void
    {
        $this->app['config']->set('laragraph.validation.rules', [
            \GraphQL\Validator\Rules\QueryDepth::class,
        ]);

        // Clear the cached instance so the service provider factory re-runs
        // with the updated config (factory uses config() lazily).
        $this->app->forgetInstance(ValidationRuleRegistry::class);

        $registry = $this->app->make(ValidationRuleRegistry::class);

        $this->assertCount(1, $registry->all());
        $this->assertSame(\GraphQL\Validator\Rules\QueryDepth::class, $registry->all()[0]);
    }
}
