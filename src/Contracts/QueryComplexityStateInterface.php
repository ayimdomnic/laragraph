<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Extensions\QueryComplexityExtension;
use Ayimdomnic\Laragraph\Extensions\QueryComplexityState;
use GraphQL\Validator\Rules\QueryComplexity;

/**
 * Holds the current execution's {@see QueryComplexity} rule instance (if one
 * ran), so {@see QueryComplexityExtension}
 * can read it without being reconstructed on every execution.
 *
 * @see QueryComplexityState the built-in implementation.
 */
interface QueryComplexityStateInterface
{
    public function setCurrent(?QueryComplexity $rule): void;

    public function current(): ?QueryComplexity;
}
