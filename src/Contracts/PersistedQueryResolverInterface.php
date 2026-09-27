<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryResolver;

/**
 * Resolves the query text for a request when persisted queries are enabled.
 *
 * @see PersistedQueryResolver the built-in implementation.
 */
interface PersistedQueryResolverInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    public function resolve(string $query, array $data): string;
}
