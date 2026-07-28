<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

/**
 * Per-request registry of user-defined {@see GraphQLExtensionInterface} implementations.
 *
 * Bound as a singleton in the container. Register your own extensions from a
 * service provider or middleware:
 *
 * ```php
 * app(\Ayimdomnic\Laragraph\Extensions\ExtensionRegistry::class)
 *     ->add(new MyExtension());
 * ```
 *
 * Built-in extensions (`request_id`, `query_timing`) are configured via
 * `laragraph.extensions` in your config and are applied automatically by
 * {@see \Ayimdomnic\Laragraph\Laragraph::execute()}.
 */
final class ExtensionRegistry
{
    /** @var list<GraphQLExtensionInterface> */
    private array $extensions = [];

    /**
     * Register a custom extension.
     */
    public function add(GraphQLExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
    }

    /**
     * Return all registered extensions.
     *
     * @return list<GraphQLExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    /**
     * Collect and key all extension data, skipping extensions that return `[]`.
     *
     * @param  array{execution_ms?: float} $context  Runtime values forwarded to each extension.
     * @return array<string, array<string, mixed>>
     */
    public function collect(array $context = []): array
    {
        $result = [];

        foreach ($this->extensions as $extension) {
            $data = $extension->get($context);
            if (!empty($data)) {
                $result[$extension->key()] = $data;
            }
        }

        return $result;
    }

    /**
     * Return `true` when no extensions have been registered.
     */
    public function isEmpty(): bool
    {
        return $this->extensions === [];
    }
}
