<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class BatchRelationPostType extends Type
{
    protected array $attributes = ['name' => 'BatchRelationPost'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'title' => GType::string(),
        ];
    }
}

class BatchRelationUserType extends Type
{
    protected array $attributes = ['name' => 'BatchRelationUser'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'name'  => GType::string(),
            'posts' => GType::listOf(app('laragraph')->type('BatchRelationPost')),
        ];
    }

    protected function resolvePostsField(mixed $root, array $args, mixed $context): mixed
    {
        return $this->batchRelation(User::class, 'posts', $root, $context);
    }
}

class BatchRelationUsersQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(app('laragraph')->type('BatchRelationUser'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::all();
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class BatchRelationTest extends TestCase
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
            'BatchRelationPost' => BatchRelationPostType::class,
            'BatchRelationUser' => BatchRelationUserType::class,
        ]);

        $app['config']->set('laragraph.schemas.default', [
            'query' => ['users' => BatchRelationUsersQuery::class],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->integer('age')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_nested_relation_field_resolves_via_a_constant_number_of_queries(): void
    {
        foreach (range(1, 6) as $i) {
            $user = User::create(['name' => "User {$i}", 'email' => "user{$i}@example.com"]);
            Post::create(['user_id' => $user->id, 'title' => "Post {$i}-a"]);
            Post::create(['user_id' => $user->id, 'title' => "Post {$i}-b"]);
        }

        DB::enableQueryLog();

        $result = $this->graphql('{ users { id name posts { id title } } }');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(6, $result['data']['users']);

        foreach ($result['data']['users'] as $user) {
            $this->assertCount(2, $user['posts']);
        }

        // 1 query for the users list + at most 2 for the batched relation
        // (parent refetch + eager relation query), regardless of user count.
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    public function test_batch_relation_throws_when_context_has_no_dataloader_registry(): void
    {
        $type = new BatchRelationUserType();
        $user = User::create(['name' => 'Solo', 'email' => 'solo@example.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('batchRelation() requires a DataLoaderRegistry');

        $method = new \ReflectionMethod($type, 'batchRelation');
        $method->invoke($type, User::class, 'posts', $user, (object) []);
    }
}
