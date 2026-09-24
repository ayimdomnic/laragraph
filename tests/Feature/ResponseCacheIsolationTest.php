<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Foundation\Auth\User;

class CacheWhoAmIQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return auth()->id() === null ? 'guest' : 'user-' . auth()->id();
    }
}

class CacheCounterMutation extends Mutation
{
    public static int $calls = 0;

    public function type(): Type
    {
        return Type::int();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ++self::$calls;
    }
}

class ResponseCacheIsolationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.cache.response.enabled', true);
        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['whoami' => CacheWhoAmIQuery::class],
            'mutation' => ['bump' => CacheCounterMutation::class],
        ]);
        $app['config']->set('laragraph.schemas.admin', [
            'query' => ['whoami' => CacheWhoAmIQuery::class],
        ]);
    }

    private function actingAsUser(int $id): void
    {
        $user = new User();
        $user->forceFill(['id' => $id]);
        $this->actingAs($user);
    }

    public function test_cached_responses_are_never_shared_between_users(): void
    {
        $this->actingAsUser(1);
        $this->assertSame('user-1', $this->graphql('{ whoami }')['data']['whoami']);

        $this->actingAsUser(2);
        $this->assertSame('user-2', $this->graphql('{ whoami }')['data']['whoami']);
    }

    public function test_global_scope_shares_entries_between_callers(): void
    {
        config(['laragraph.cache.response.scope' => 'global']);

        $this->actingAsUser(1);
        $this->graphql('{ whoami }');

        $this->actingAsUser(2);
        $this->assertSame('user-1', $this->graphql('{ whoami }')['data']['whoami']);
    }

    public function test_mutations_hidden_behind_a_comment_are_not_cached(): void
    {
        CacheCounterMutation::$calls = 0;
        $document = "# not a query\nmutation { bump }";

        $this->assertSame(1, $this->graphql($document)['data']['bump']);
        $this->assertSame(2, $this->graphql($document)['data']['bump']);
    }

    public function test_mutations_selected_by_operation_name_are_not_cached(): void
    {
        CacheCounterMutation::$calls = 0;
        $document = 'query Read { whoami } mutation Write { bump }';

        $first  = $this->postJson('/graphql', ['query' => $document, 'operationName' => 'Write'])->json('data.bump');
        $second = $this->postJson('/graphql', ['query' => $document, 'operationName' => 'Write'])->json('data.bump');

        $this->assertSame([1, 2], [$first, $second]);
    }
}
