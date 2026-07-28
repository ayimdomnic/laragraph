<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * Thrown when a batch request contains more operations than the configured maximum.
 */
class BatchLimitExceededException extends \RuntimeException
{
    public function __construct(int $limit)
    {
        parent::__construct("Batch size exceeds the maximum of {$limit} operations.");
    }
}
