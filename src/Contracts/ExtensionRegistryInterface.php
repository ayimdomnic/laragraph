<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;

/**
 * Per-request registry of user-defined and built-in {@see GraphQLExtensionInterface} implementations.
 *
 * @see ExtensionRegistry the built-in implementation.
 */
interface ExtensionRegistryInterface
{
    /**
     * Register an extension.
     */
    public function add(GraphQLExtensionInterface $extension): void;

    /**
     * Return all registered extensions.
     *
     * @return list<GraphQLExtensionInterface>
     */
    public function all(): array;

    /**
     * Collect and key all extension data, skipping extensions that return `[]`.
     *
     * @param array{execution_ms?: float} $context Runtime values forwarded to each extension.
     * @return array<string, array<string, mixed>>
     */
    public function collect(array $context = []): array;

    /**
     * Return `true` when no extensions have been registered.
     */
    public function isEmpty(): bool;
}
