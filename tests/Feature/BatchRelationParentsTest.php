<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class ParentsPostType extends Type
{
    protected array $attributes = ['name' => 'ParentsPost'];

    public function fields(): array
    {
        return ['title' => GType::string()];
    }
}

class ParentsUserType extends Type
{
    protected array $attributes = ['name' => 'ParentsUser'];

    public function fields(): array
    {
        return [
            'name'  => GType::string(),
            'posts' => GType::listOf(app('laragraph')->type('ParentsPost')),
        ];
    }

    protected function resolvePostsField(mixed $root, array $args, mixed $context): mixed
    {
        return $this->batchRelation(User::class, 'posts', $root, $context);
    }
}

class ParentsUsersQuery extends Query
{
    /** @var \Closure(): iterable<User> */
    public static \Closure $source;

    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('ParentsUser'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (self::$source)();
    }
}

class BatchRelationParentsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('laragraph.types', ['ParentsPost' => ParentsPostType::class, 'ParentsUser' => ParentsUserType::class]);
        $app['config']->set('laragraph.schemas.default', ['query' => ['users' => ParentsUsersQuery::class]]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
        Schema::create('posts', function ($table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        foreach (range(1, 5) as $i) {
            $user = User::create(['name' => "User {$i}", 'email' => "u{$i}@example.com", 'age' => $i * 10]);
            Post::create(['user_id' => $user->id, 'title' => "Post {$i}"]);
        }
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function fetchUsers(): array
    {
        DB::enableQueryLog();
        $result = $this->graphql('{ users { name posts { title } } }');
        $count  = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertArrayNotHasKey('errors', $result, json_encode($result));

        return [$result, $count];
    }

    public function test_parents_are_not_queried_again(): void
    {
        ParentsUsersQuery::$source = fn() => User::all();

        [$result, $queries] = $this->fetchUsers();

        $this->assertSame(['Post 1'], array_column($result['data']['users'][0]['posts'], 'title'));
        $this->assertSame(2, $queries); // the users list + one batched posts query
    }

    public function test_already_eager_loaded_relations_cost_no_query(): void
    {
        ParentsUsersQuery::$source = fn() => User::with('posts')->get();

        [, $queries] = $this->fetchUsers();

        $this->assertSame(2, $queries); // User::with('posts') itself: users + posts, nothing more
    }

    public function test_parents_hidden_by_a_global_scope_still_resolve(): void
    {
        User::addGlobalScope('adults', fn(Builder $query) => $query->where('age', '>=', 30));
        ParentsUsersQuery::$source = fn() => User::withoutGlobalScope('adults')->orderBy('id')->get();

        [$result] = $this->fetchUsers();

        // User 1 (age 10) would be missing from a fresh, scoped parent query.
        $this->assertSame('User 1', $result['data']['users'][0]['name']);
        $this->assertSame(['Post 1'], array_column($result['data']['users'][0]['posts'], 'title'));
    }
}
