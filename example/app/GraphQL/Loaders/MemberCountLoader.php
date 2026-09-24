<?php

declare(strict_types=1);

namespace App\GraphQL\Loaders;

use App\Models\User;
use Ayimdomnic\Laragraph\DataLoader\BatchResolver;

/**
 * CUSTOM DATALOADER — counts members for every requested organization in a
 * single grouped query. Use a BatchResolver when the data is not a plain
 * Eloquent relation (aggregates, external APIs, computed values).
 */
class MemberCountLoader extends BatchResolver
{
    /**
     * @param  array<int|string>  $keys  Organization ids.
     * @return array<int, int> One result per key, in the same order.
     */
    public function batch(array $keys): array
    {
        $counts = User::query()
            ->whereIn('organization_id', $keys)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        return array_map(fn (int|string $id): int => (int) ($counts[$id] ?? 0), $keys);
    }
}
