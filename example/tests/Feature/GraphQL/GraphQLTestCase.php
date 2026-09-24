<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Helpers for exercising the GraphQL API exactly as a client would: over
 * HTTP, with a JWT in the Authorization header.
 */
abstract class GraphQLTestCase extends TestCase
{
    use RefreshDatabase;

    protected Organization $acme;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->admin = User::factory()->admin()->in($this->acme)->create(['name' => 'Ada Admin']);
        $this->member = User::factory()->in($this->acme)->create(['name' => 'Mo Member']);
    }

    /**
     * Each test request must start unauthenticated, like a real HTTP request:
     * the test application (and its JWT guard) otherwise remembers the
     * previous request's token and user.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();
        JWTAuth::unsetToken();                   // the JWTAuth facade's token…
        $this->app['tymon.jwt']->unsetToken();   // …and the one the JWT guard reads

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected function graphql(string $query, array $variables = [], ?User $as = null, string $endpoint = '/graphql'): TestResponse
    {
        return $this->postJson($endpoint, ['query' => $query, 'variables' => $variables], $this->headersFor($as));
    }

    /**
     * @return array<string, string>
     */
    protected function headersFor(?User $user): array
    {
        return $user === null ? [] : ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
