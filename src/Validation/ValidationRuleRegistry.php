<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Validation;

use GraphQL\Validator\Rules\ValidationRule;

/**
 * Stores user-defined (and config-declared) GraphQL validation rules.
 *
 * Rules registered here are merged into every execution's rule set by
 * {@see \Ayimdomnic\Laragraph\Laragraph::buildValidationRules()}.
 *
 * Usage:
 *
 *   // At any time before execution:
 *   app(ValidationRuleRegistry::class)->add(new MyCustomRule());
 *   app(ValidationRuleRegistry::class)->add(MyCustomRule::class);
 *
 *   // Or in a service provider:
 *   Laragraph::addValidationRule(new MyCustomRule());
 */
class ValidationRuleRegistry
{
    /** @var array<int, string|ValidationRule> */
    protected array $rules = [];

    /**
     * Register a validation rule.
     *
     * @param  string|ValidationRule $rule  A FQCN string (resolved via the
     *                                      container) or a concrete instance.
     */
    public function add(string|ValidationRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Return all registered rules, resolving FQCN strings via the container.
     *
     * @return array<int, ValidationRule>
     */
    public function resolve(): array
    {
        return array_values(array_map(
            fn (string|ValidationRule $rule) => is_string($rule) ? app($rule) : $rule,
            $this->rules,
        ));
    }

    /** Return all registered entries (unresolved).
     *
     * @return array<int, string|ValidationRule>
     */
    public function all(): array
    {
        return $this->rules;
    }

    public function isEmpty(): bool
    {
        return empty($this->rules);
    }
}
