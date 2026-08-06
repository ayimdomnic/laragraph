<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\DataLoader;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Overblog\DataLoader\DataLoader;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class EloquentRelationLoaderTest extends TestCase
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

    public function test_batches_a_hasmany_relation_in_a_constant_number_of_queries(): void
    {
        $users = collect(range(1, 5))->map(fn (int $i) => User::create([
            'name'  => "User {$i}",
            'email' => "user{$i}@example.com",
        ]));

        foreach ($users as $user) {
            Post::create(['user_id' => $user->id, 'title' => "Post by {$user->id}-a"]);
            Post::create(['user_id' => $user->id, 'title' => "Post by {$user->id}-b"]);
        }

        $registry = new DataLoaderRegistry();
        $loader   = $registry->relation(User::class, 'posts');

        DB::enableQueryLog();

        $promises = $users->map(fn (User $user) => $loader->load($user->id));
        $results  = $promises->map(fn ($promise) => DataLoader::await($promise));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Regardless of how many parents were loaded, the relation resolves
        // in a fixed, small number of queries (parent refetch + eager relation
        // query) instead of one query per parent (N+1).
        $this->assertLessThanOrEqual(2, $queryCount);

        foreach ($results as $i => $posts) {
            $user = $users[$i];
            $this->assertCount(2, $posts);
            foreach ($posts as $post) {
                $this->assertSame($user->id, $post->user_id);
            }
        }
    }

    public function test_batches_a_belongsto_relation(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob   = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $post1 = Post::create(['user_id' => $alice->id, 'title' => 'Post 1']);
        $post2 = Post::create(['user_id' => $bob->id, 'title' => 'Post 2']);

        $registry = new DataLoaderRegistry();
        $loader   = $registry->relation(Post::class, 'author');

        $author1 = DataLoader::await($loader->load($post1->id));
        $author2 = DataLoader::await($loader->load($post2->id));

        $this->assertSame($alice->id, $author1->id);
        $this->assertSame($bob->id, $author2->id);
    }

    public function test_relation_returns_null_for_unknown_keys(): void
    {
        $registry = new DataLoaderRegistry();
        $loader   = $registry->relation(Post::class, 'author');

        $result = DataLoader::await($loader->load(999999));

        $this->assertNull($result);
    }
}
