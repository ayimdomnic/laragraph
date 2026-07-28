<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * Thrown when a batch request is received but batching is disabled in config.
 */
class BatchingDisabledException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('GraphQL batch requests are disabled.');
    }
}
