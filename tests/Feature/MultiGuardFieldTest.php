<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Auth\AuthorizationContext;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Auth\GenericUser;

class MultiGuardQuery extends Query
{
    public static ?string $seenGuard = null;

    public function type(): Type
    {
        return Type::string();
    }

    public function guards(): array
    {
        return ['web', 'admin'];
    }

    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        self::$seenGuard = $ctx->guardName();

        return $ctx->check();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'hello ' . self::$seenGuard;
    }
}

class MultiGuardFieldTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('laragraph.schemas.default.query', ['greeting' => MultiGuardQuery::class]);
    }

    public function test_the_first_authenticated_guard_is_used(): void
    {
        $this->actingAs(new GenericUser(['id' => 7]), 'admin');

        $this->assertSame('hello admin', $this->graphql('{ greeting }')['data']['greeting'] ?? null);
    }

    public function test_earlier_guards_win_when_several_authenticate(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]), 'web');
        $this->actingAs(new GenericUser(['id' => 7]), 'admin');

        $this->assertSame('hello web', $this->graphql('{ greeting }')['data']['greeting'] ?? null);
    }

    public function test_guests_are_checked_against_the_first_guard(): void
    {
        $result = $this->graphql('{ greeting }');

        $this->assertSame('web', MultiGuardQuery::$seenGuard);
        $this->assertSame('authorization', $result['errors'][0]['extensions']['category'] ?? null);
    }
}
