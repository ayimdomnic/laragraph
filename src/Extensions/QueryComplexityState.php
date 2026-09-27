<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

use Ayimdomnic\Laragraph\Contracts\QueryComplexityStateInterface;
use Ayimdomnic\Laragraph\Execution\QueryExecutor;
use GraphQL\Validator\Rules\QueryComplexity;

/**
 * Holds the current execution's {@see QueryComplexity} rule instance, so
 * {@see QueryComplexityExtension} can read it without being reconstructed
 * (with a fresh rule injected) on every single execution.
 *
 * Bound as a container singleton — set once per execution by
 * {@see QueryExecutor::partitionValidationRules()}.
 */
final class QueryComplexityState implements QueryComplexityStateInterface
{
    private ?QueryComplexity $current = null;

    public function setCurrent(?QueryComplexity $rule): void
    {
        $this->current = $rule;
    }

    public function current(): ?QueryComplexity
    {
        return $this->current;
    }
}
