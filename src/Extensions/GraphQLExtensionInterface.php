<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

interface GraphQLExtensionInterface
{
    /**
     * The top-level key under which this extension's data appears in
     * `response.extensions`.
     */
    public function key(): string;

    /**
     * Return the extension data to place under {@see key()}.
     *
     * Runtime values supplied by the execution engine are passed in `$context`:
     *
     * - `execution_ms` (float) — total `execute()` wall-clock time in ms
     *
     * @param  array{execution_ms?: float} $context
     * @return array<string, mixed>
     */
    public function get(array $context = []): array;
}
