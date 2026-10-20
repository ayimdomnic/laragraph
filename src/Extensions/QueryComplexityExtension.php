<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

use Ayimdomnic\Laragraph\Contracts\QueryComplexityStateInterface;
use GraphQL\Validator\Rules\QueryComplexity;

/**
 * Reports the computed query cost under `extensions.queryComplexity`, so
 * clients can see how close they are to `laragraph.security.query_max_complexity`
 * and self-throttle — the same idea as GitHub's or Shopify's GraphQL APIs
 * exposing a rate-limit cost in their response.
 *
 * Populated even when the query was rejected for exceeding the limit (per
 * {@see QueryComplexity::getQueryComplexity()}'s own contract), so a client
 * can see exactly how far over budget a rejected query was. When the query
 * died before the cost walk ran at all (parse or earlier validation
 * failure), `cost` reports as `null` rather than fataling or faking a `0`.
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
    public function __construct(private QueryComplexityStateInterface $state) {}

    public function key(): string
    {
        return 'queryComplexity';
    }

    /**
     * @return array{cost: int|null, maxCost: int}|array{}
     */
    public function get(array $context = []): array
    {
        $rule = $this->state->current();

        if (!$rule instanceof QueryComplexity) {
            return [];
        }

        // Webonyx only initialises the running cost inside getVisitor() — a
        // rule that never walked a document (parse failure, or validation
        // aborted before this rule ran) would fatal with "must not be
        // accessed before initialization". Unknown cost reports as null,
        // not as 0, so clients can tell "not computed" from "free".
        try {
            $cost = $rule->getQueryComplexity();
        } catch (\Error) {
            $cost = null;
        }

        return [
            'cost'    => $cost,
            'maxCost' => $rule->getMaxQueryComplexity(),
        ];
    }
}
