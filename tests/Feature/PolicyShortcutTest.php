<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

class Article extends Model {}

class ArticlePolicy
{
    /** @var list<string> */
    public static array $calls = [];

    public function viewAny(Authenticatable $user): bool
    {
        self::$calls[] = 'viewAny';

        return $user->getAuthIdentifier() === 1;
    }

    public function browse(?Authenticatable $user): bool
    {
        self::$calls[] = 'browse';

        return true;
    }
}

class SuperAdminPolicy
{
    public function before(?Authenticatable $user, string $ability): ?bool
    {
        return $user?->getAuthIdentifier() === 42 ? true : null;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return false;
    }
}

class PolicyShortcutQuery extends Query
{
    public static string $policy  = ArticlePolicy::class;
    public static string $ability = 'viewAny';

    public function type(): Type
    {
        return Type::string();
    }

    public function policy(): ?string
    {
        return self::$policy;
    }

    public function policyAbility(): string
    {
        return self::$ability;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'allowed';
    }
}

class PolicyShortcutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', ['articles' => PolicyShortcutQuery::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        ArticlePolicy::$calls         = [];
        PolicyShortcutQuery::$policy  = ArticlePolicy::class;
        PolicyShortcutQuery::$ability = 'viewAny';
    }

    private function as(int $id): void
    {
        $this->actingAs((new User())->forceFill(['id' => $id]));
    }

    private function allowed(): bool
    {
        return ($this->graphql('{ articles }')['data']['articles'] ?? null) === 'allowed';
    }

    public function test_an_unregistered_policy_class_is_actually_called(): void
    {
        $this->as(1);
        $this->assertTrue($this->allowed());

        $this->as(2);
        $this->assertFalse($this->allowed());

        $this->assertSame(['viewAny', 'viewAny'], ArticlePolicy::$calls);
    }

    public function test_a_registered_policy_class_goes_through_the_gate(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::before(fn(Authenticatable $user): ?bool => $user->getAuthIdentifier() === 99 ? true : null);

        $this->as(99); // allowed by the Gate::before() hook, which a direct call would skip
        $this->assertTrue($this->allowed());
        $this->assertSame([], ArticlePolicy::$calls);

        $this->as(1);
        $this->assertTrue($this->allowed());
    }

    public function test_the_model_class_may_be_given_instead_of_the_policy(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        PolicyShortcutQuery::$policy = Article::class;

        $this->as(1);
        $this->assertTrue($this->allowed());

        $this->as(2);
        $this->assertFalse($this->allowed());
    }

    public function test_guests_are_denied_unless_the_policy_accepts_them(): void
    {
        $this->assertFalse($this->allowed());
        $this->assertSame([], ArticlePolicy::$calls);

        PolicyShortcutQuery::$ability = 'browse';
        $this->assertTrue($this->allowed());
    }

    public function test_a_policy_before_method_is_honoured(): void
    {
        PolicyShortcutQuery::$policy = SuperAdminPolicy::class;

        $this->as(42);
        $this->assertTrue($this->allowed());

        $this->as(1);
        $this->assertFalse($this->allowed());
    }

    public function test_unknown_abilities_and_classes_are_denied(): void
    {
        $this->as(1);

        PolicyShortcutQuery::$ability = 'doesNotExist';
        $this->assertFalse($this->allowed());

        PolicyShortcutQuery::$policy = 'App\\Policies\\MissingPolicy';
        $this->assertFalse($this->allowed());
    }
}
