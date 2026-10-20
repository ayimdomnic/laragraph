<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Auth\FieldVisibility;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

// ---------------------------------------------------------------------------
// Fixtures — a User with a batch-loaded posts relation, where both the
// relation field and one of its child fields declare canSee.
// ---------------------------------------------------------------------------

class CsBatchToggle
{
    public static bool $viewPosts = true;

    public static bool $viewBody = false;

    public static function reset(): void
    {
        self::$viewPosts = true;
        self::$viewBody = false;
    }
}

class CsBatchPostType extends Type
{
    protected array $attributes = ['name' => 'CsBatchPost'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'title' => GType::string(),
            'body'  => ['type' => GType::string(), 'canSee' => 'viewBody'],
        ];
    }
}

class CsBatchUserType extends Type
{
    protected array $attributes = ['name' => 'CsBatchUser'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'name'  => GType::string(),
            'posts' => ['type' => GType::listOf(app('laragraph')->type('CsBatchPost')), 'canSee' => 'viewPosts'],
        ];
    }

    protected function resolvePostsField(mixed $root, array $args, mixed $context): mixed
    {
        return $this->batchRelation(User::class, 'posts', $root, $context);
    }
}

class CsBatchUsersQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('CsBatchUser'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::all();
    }
}

class FieldCanSeeBatchRelationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('laragraph.types', [
            'CsBatchPost' => CsBatchPostType::class,
            'CsBatchUser' => CsBatchUserType::class,
        ]);

        $app['config']->set('laragraph.schemas.default', [
            'query' => ['users' => CsBatchUsersQuery::class],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        CsBatchToggle::reset();
        FieldVisibility::reset();

        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('posts', function ($table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        foreach (range(1, 3) as $i) {
            $user = User::create(['name' => "User {$i}", 'email' => "user{$i}@example.com"]);
            Post::create(['user_id' => $user->id, 'title' => "Post {$i}-a", 'body' => "Body {$i}-a"]);
            Post::create(['user_id' => $user->id, 'title' => "Post {$i}-b", 'body' => "Body {$i}-b"]);
        }

        $this->actingAs((new AuthUser())->forceFill(['id' => 1]));

        Gate::define('viewPosts', fn(Authenticatable $user): bool => CsBatchToggle::$viewPosts);
        Gate::define('viewBody', fn(Authenticatable $user): bool => CsBatchToggle::$viewBody);
    }

    /**
     * @return array{result: array, queries: int}
     */
    private function queryAndCount(string $query): array
    {
        DB::enableQueryLog();

        $result = $this->graphql($query);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return ['result' => $result, 'queries' => $queries];
    }

    public function test_children_with_can_see_still_batch_into_a_single_relation_query(): void
    {
        ['result' => $result, 'queries' => $queries] = $this->queryAndCount('{ users { id posts { title body } } }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(3, $result['data']['users']);

        // 1 users query + 1 batched posts query — the per-child canSee check on
        // `body` runs at resolution time and costs no extra SQL.
        $this->assertSame(2, $queries);

        foreach ($result['data']['users'] as $user) {
            $this->assertCount(2, $user['posts']);

            foreach ($user['posts'] as $post) {
                $this->assertStringStartsWith('Post', $post['title']);
                $this->assertNull($post['body']); // viewBody denies by default
            }
        }
    }

    public function test_allowed_child_visibility_resolves_values_with_the_same_single_batch(): void
    {
        CsBatchToggle::$viewBody = true;

        ['result' => $result, 'queries' => $queries] = $this->queryAndCount('{ users { posts { body } } }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(2, $queries);

        foreach ($result['data']['users'] as $user) {
            foreach ($user['posts'] as $post) {
                $this->assertStringStartsWith('Body', $post['body']);
            }
        }
    }

    public function test_a_denied_relation_field_skips_the_batch_query_entirely(): void
    {
        CsBatchToggle::$viewPosts = false;

        ['result' => $result, 'queries' => $queries] = $this->queryAndCount('{ users { id posts { title } } }');

        $this->assertArrayNotHasKey('errors', $result);

        // The parent fails `viewPosts`, so the DataLoader is never even asked —
        // the relation query disappears along with the values.
        $this->assertSame(1, $queries);

        foreach ($result['data']['users'] as $user) {
            $this->assertNull($user['posts']);
        }
    }
}
