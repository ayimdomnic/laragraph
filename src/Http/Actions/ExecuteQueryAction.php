<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Ayimdomnic\Laragraph\Contracts\BatchProcessorInterface;
use Ayimdomnic\Laragraph\Contracts\PersistedQueryResolverInterface;
use Ayimdomnic\Laragraph\Contracts\QueryExecutorInterface;
use Ayimdomnic\Laragraph\Contracts\RequestParserInterface;
use Ayimdomnic\Laragraph\Contracts\ResponseNegotiatorInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionRequestHandlerInterface;
use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\Support\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Executes a GraphQL query / mutation over HTTP.
 *
 * Follows the GraphQL-over-HTTP specification: clients that send
 * `Accept: application/graphql-response+json` receive that media type and
 * a 4xx status whenever the request fails before execution; others get
 * `application/json` with the traditional always-200 behaviour.
 */
final readonly class ExecuteQueryAction
{
    public function __construct(
        private RequestParserInterface $parser,
        private ResponseNegotiatorInterface $negotiator,
        private PersistedQueryResolverInterface $persistedQueries,
        private SubscriptionRequestHandlerInterface $subscriptions,
        private BatchProcessorInterface $batches,
        private QueryExecutorInterface $executor,
        private StreamSubscriptionOperationAction $sseSubscription,
    ) {}

    public function handle(Request $request, string $schemaName = 'default'): JsonResponse|StreamedResponse
    {
        try {
            if (config("laragraph.schemas.{$schemaName}") === null) {
                throw RequestException::schemaNotFound($schemaName);
            }

            $parsed = $this->parser->parse($request);

            // Batched queries: a JSON list of operations. A single SSE stream
            // can't sensibly carry N independent subscriptions' frames, so
            // graphql-sse compliance only ever applies to a single operation.
            if ($parsed !== [] && array_is_list($parsed)) {
                array_walk($parsed, $this->parser->assertOperation(...));

                // BatchingDisabledException / BatchLimitExceededException extend
                // RequestException, so they fall through to the catch below.
                $results = $this->batches->process(
                    $parsed,
                    $request,
                    $schemaName,
                    function (array $operation) use ($request, $schemaName): array {
                        try {
                            return $this->executeOne($operation, $request, $schemaName);
                        } catch (RequestException $e) {
                            return $e->toResponse();
                        }
                    },
                );

                return $this->negotiator->negotiate($request, $results, 200);
            }

            $this->parser->assertOperation($parsed);

            if ($this->wantsGraphqlSse($parsed, $request)) {
                return $this->sseSubscription->handle(
                    (string) ($parsed['query'] ?? ''),
                    $this->parser->castVariables($parsed['variables'] ?? null),
                    isset($parsed['operationName']) ? (string) $parsed['operationName'] : null,
                    $schemaName,
                    $request,
                );
            }

            $result = $this->executeOne($parsed, $request, $schemaName);
        } catch (RequestException $e) {
            return $this->negotiator->negotiate($request, $e->toResponse(), $e->status, $e->headers);
        }

        return $this->negotiator->negotiate($request, $result);
    }

    /**
     * Whether this request should get a graphql-sse-compliant stream
     * instead of the pre-existing "register, then poll a separate
     * `streamUrl`" flow — only when the client explicitly asks for it
     * (`Accept: text/event-stream`), the driver actually supports it, and
     * the operation is (by its own text) a subscription. Persisted-query
     * references (`queryId` with no inline `query`) aren't recognised here
     * — detecting a subscription requires resolving the query text first,
     * which only `executeOne()` does; those fall back to the pre-existing
     * flow, which resolves persisted queries before this check would apply.
     *
     * @param array<string, mixed> $parsed
     */
    private function wantsGraphqlSse(array $parsed, Request $request): bool
    {
        $query = (string) ($parsed['query'] ?? '');

        if ($query === '' || config('laragraph.subscriptions.driver', 'broadcast') !== 'sse') {
            return false;
        }

        $operationName = isset($parsed['operationName']) ? (string) $parsed['operationName'] : null;

        if (!$this->subscriptions->isSubscriptionOperation($query, $operationName)) {
            return false;
        }

        return str_contains(strtolower((string) $request->header('Accept', '')), 'text/event-stream');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws RequestException When the request is rejected before execution.
     */
    private function executeOne(array $data, Request $request, string $schemaName): array
    {
        $query         = (string) ($data['query'] ?? '');
        $variables     = $this->parser->castVariables($data['variables'] ?? null);
        $operationName = isset($data['operationName']) ? (string) $data['operationName'] : null;

        if (config('laragraph.persisted_queries.enabled', false)) {
            $query = $this->persistedQueries->resolve($query, $data);
        }

        // GraphQL-over-HTTP: GET must never execute a mutation (it would be CSRF-able).
        if ($request->isMethod('GET') && Operation::isMutation($query, $operationName)) {
            throw RequestException::methodNotAllowed();
        }

        if ($query !== '' && $this->subscriptions->isSubscriptionOperation($query, $operationName)) {
            return $this->subscriptions->register($query, $variables, $operationName, $schemaName, $request);
        }

        return $this->executor->execute(
            query: $query,
            context: $request,
            variables: $variables,
            operationName: $operationName,
            schemaName: $schemaName,
        );
    }
}
