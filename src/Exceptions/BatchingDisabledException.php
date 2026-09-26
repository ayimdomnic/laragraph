<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * Thrown when a batch request is received but batching is disabled in config.
 */
class BatchingDisabledException extends RequestException
{
    public function __construct()
    {
        parent::__construct(trans('laragraph::errors.batching.disabled'), 'BATCHING_DISABLED', 400);
    }
}
