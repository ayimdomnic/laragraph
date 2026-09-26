<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Testing;

use Illuminate\Testing\TestResponse;

/**
 * A shippable helper for testing your OWN app's GraphQL API — add this trait
 * to your app's test base class. This has nothing to do with Laragraph's own
 * internal test suite (`tests/TestCase.php` in this repository), which only
 * exists to test the package itself and is never autoloaded into a consumer
 * application.
 *
 * ```php
 * abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
 * {
 *     use \Ayimdomnic\Laragraph\Testing\MakesGraphQLRequests;
 *
 *     // Laragraph doesn't know which auth package you use — override this
 *     // once for your own scheme (JWT shown; Sanctum/session look similar).
 *     protected function graphqlAuthHeaders(mixed $as): array
 *     {
 *         return $as === null ? [] : ['Authorization' => 'Bearer ' . \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($as)];
 *     }
 * }
 * ```
 *
 * Then in a test:
 * ```php
 * $this->graphql('query ($id: ID!) { user(id: $id) { email } }', ['id' => $this->member->id], as: $this->member)
 *     ->assertNoGraphQLErrors()
 *     ->assertGraphQLData('user.email', $this->member->email);
 * ```
 */
trait MakesGraphQLRequests
{
    /**
     * @param  array<string, mixed>  $variables
     */
    protected function graphql(string $query, array $variables = [], mixed $as = null, string $endpoint = '/graphql'): TestResponse
    {
        return $this->postJson($endpoint, ['query' => $query, 'variables' => $variables], $this->graphqlAuthHeaders($as));
    }

    /**
     * Translate $as (a user, a token, whatever your app's `graphql()` calls
     * pass) into request headers for your own auth scheme. The default is
     * unauthenticated, since Laragraph itself has no opinion on JWT vs.
     * Sanctum vs. session — override this once in your test base class.
     *
     * @return array<string, string>
     */
    protected function graphqlAuthHeaders(mixed $as): array
    {
        return [];
    }
}
