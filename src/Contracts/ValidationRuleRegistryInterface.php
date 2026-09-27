<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Stores user-defined (and config-declared) GraphQL validation rules.
 *
 * @see ValidationRuleRegistry the built-in implementation.
 */
interface ValidationRuleRegistryInterface
{
    /**
     * Register a validation rule.
     *
     * @param string|ValidationRule $rule A FQCN string (resolved via the container) or an instance.
     */
    public function add(string|ValidationRule $rule): void;

    /**
     * Return all registered rules, resolving FQCN strings via the container.
     *
     * @return array<int, ValidationRule>
     */
    public function resolve(): array;

    /**
     * Return all registered entries (unresolved).
     *
     * @return array<int, string|ValidationRule>
     */
    public function all(): array;

    public function isEmpty(): bool;
}
