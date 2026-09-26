<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * Thrown when a batch request contains more operations than the configured maximum.
 */
class BatchLimitExceededException extends RequestException
{
    public function __construct(int $limit)
    {
        parent::__construct(
            trans('laragraph::errors.batching.limit_exceeded', ['limit' => $limit]),
            'BATCH_LIMIT_EXCEEDED',
            400,
        );
    }
}
