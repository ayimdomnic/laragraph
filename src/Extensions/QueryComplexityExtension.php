<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

use GraphQL\Validator\Rules\QueryComplexity;

/**
 * Reports the computed query cost under `extensions.queryComplexity`, so
 * clients can see how close they are to `laragraph.security.query_max_complexity`
 * and self-throttle — the same idea as GitHub's or Shopify's GraphQL APIs
 * exposing a rate-limit cost in their response.
 *
 * Populated even when the query was rejected for exceeding the limit (per
 * {@see QueryComplexity::getQueryComplexity()}'s own contract), so a client
 * can see exactly how far over budget a rejected query was.
 *
 * Enable via config:
 * ```php
 * 'extensions' => ['query_complexity' => true],
 * ```
 *
 * Response shape:
 * ```json
 * { "extensions": { "queryComplexity": { "cost": 42, "maxCost": 500 } } }
 * ```
 */
final readonly class QueryComplexityExtension implements GraphQLExtensionInterface
{
    public function __construct(private ?QueryComplexity $rule) {}

    public function key(): string
    {
        return 'queryComplexity';
    }

    /**
     * @return array{cost: int, maxCost: int}|array{}
     */
    public function get(array $context = []): array
    {
        if (!$this->rule instanceof QueryComplexity) {
            return [];
        }

        return [
            'cost'    => $this->rule->getQueryComplexity(),
            'maxCost' => $this->rule->getMaxQueryComplexity(),
        ];
    }
}
