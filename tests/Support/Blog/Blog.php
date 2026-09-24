<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Support\Blog;

use Ayimdomnic\Laragraph\DataLoader\BatchResolver;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A small blog domain — organizations, authors and posts — used by the
 * performance tests and the benchmarks to exercise real Eloquent workloads:
 * lists, nested batched relations, a custom DataLoader and a validated
 * mutation.
 */
final class Blog
{
    /** The operations the budgets and benchmarks run. */
    public const LIST = '{ posts(first: 50) { edges { node { id title views published createdAt } } pageInfo { hasNextPage total } } }';

    public const NESTED = '{ organizations { name authorCount authors { name posts { title author { name } } } } }';

    public const MUTATION = 'mutation { renameOrganization(id: 1, name: "Renamed") { id name } }';

    /**
     * @return array{types: array<string, class-string>, query: array<string, class-string>, mutation: array<string, class-string>}
     */
    public static function config(): array
    {
        return [
            'types' => [
                'BlogOrganization' => OrganizationType::class,
                'BlogAuthor'       => AuthorType::class,
                'BlogPost'         => PostType::class,
            ],
            'query' => [
                'posts'         => PostsQuery::class,
                'organizations' => OrganizationsQuery::class,
            ],
            'mutation' => [
                'renameOrganization' => RenameOrganizationMutation::class,
            ],
        ];
    }

    public static function migrate(): void
    {
        Schema::create('blog_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('blog_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->index();
            $table->string('name');
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->index();
            $table->string('title');
            $table->integer('views');
            $table->boolean('published');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Replace the data with $organizations organizations, each with $authors
     * authors of $posts posts. Uses bulk inserts, so seeding cost stays out
     * of timings.
     */
    public static function seed(int $organizations, int $authors = 5, int $posts = 4): void
    {
        foreach (['blog_posts', 'blog_authors', 'blog_organizations'] as $table) {
            DB::table($table)->delete();
        }

        $now = now()->toDateTimeString();
        $orgRows = $authorRows = $postRows = [];
        $authorId = 0;

        for ($o = 1; $o <= $organizations; $o++) {
            $orgRows[] = ['id' => $o, 'name' => "Organization {$o}"];

            for ($a = 0; $a < $authors; $a++) {
                $authorRows[] = ['id' => ++$authorId, 'organization_id' => $o, 'name' => "Author {$authorId}"];

                for ($p = 0; $p < $posts; $p++) {
                    $postRows[] = ['author_id' => $authorId, 'title' => "Post {$authorId}.{$p}", 'views' => $p * 10, 'published' => $p % 2 === 0, 'created_at' => $now];
                }
            }
        }

        foreach ([['blog_organizations', $orgRows], ['blog_authors', $authorRows], ['blog_posts', $postRows]] as [$table, $rows]) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Models
// ---------------------------------------------------------------------------

class Organization extends Model
{
    public $timestamps = false;

    protected $table = 'blog_organizations';

    protected $guarded = [];

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class, 'organization_id');
    }
}

class Author extends Model
{
    public $timestamps = false;

    protected $table = 'blog_authors';

    protected $guarded = [];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }
}

class Post extends Model
{
    public $timestamps = false;

    protected $table = 'blog_posts';

    protected $guarded = [];

    protected $casts = ['published' => 'boolean', 'views' => 'integer', 'created_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }
}

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

class OrganizationType extends Type
{
    protected array $attributes = ['name' => 'BlogOrganization'];

    public function fields(): array
    {
        return [
            'id'          => GType::nonNull(GType::id()),
            'name'        => GType::nonNull(GType::string()),
            'authorCount' => GType::nonNull(GType::int()),
            'authors'     => GType::listOf(Laragraph::type('BlogAuthor')),
        ];
    }

    protected function resolveAuthorsField(Organization $organization, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Organization::class, 'authors', $organization, $context);
    }

    protected function resolveAuthorCountField(Organization $organization, array $args, mixed $context): mixed
    {
        return DataLoaderRegistry::for($context)?->get(AuthorCountLoader::class)->load($organization->id);
    }
}

class AuthorType extends Type
{
    protected array $attributes = ['name' => 'BlogAuthor'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'name'  => GType::nonNull(GType::string()),
            'posts' => GType::listOf(Laragraph::type('BlogPost')),
        ];
    }

    protected function resolvePostsField(Author $author, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Author::class, 'posts', $author, $context);
    }
}

class PostType extends Type
{
    protected array $attributes = ['name' => 'BlogPost'];

    public function fields(): array
    {
        return [
            'id'        => GType::nonNull(GType::id()),
            'title'     => GType::nonNull(GType::string()),
            'views'     => GType::nonNull(GType::int()),
            'published' => GType::nonNull(GType::boolean()),
            'createdAt' => GType::string(),
            'author'    => Laragraph::type('BlogAuthor'),
        ];
    }

    protected function resolveCreatedAtField(Post $post): ?string
    {
        return $post->created_at?->toIso8601String();
    }

    protected function resolveAuthorField(Post $post, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Post::class, 'author', $post, $context);
    }
}

class AuthorCountLoader extends BatchResolver
{
    public function batch(array $keys): array
    {
        $counts = Author::query()
            ->whereIn('organization_id', $keys)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        return array_map(fn(int|string $id): int => (int) ($counts[$id] ?? 0), $keys);
    }
}

// ---------------------------------------------------------------------------
// Operations
// ---------------------------------------------------------------------------

class PostsQuery extends Query
{
    public function type(): GType
    {
        return ConnectionType::make('BlogPostConnection', Laragraph::type('BlogPost'));
    }

    public function args(): array
    {
        return ConnectionType::args();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ConnectionType::paginate(Post::query()->orderBy('id'), $args);
    }
}

class OrganizationsQuery extends Query
{
    public function type(): GType
    {
        return GType::listOf(Laragraph::type('BlogOrganization'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return Organization::query()->orderBy('id')->get();
    }
}

class RenameOrganizationMutation extends Mutation
{
    public function type(): GType
    {
        return Laragraph::type('BlogOrganization');
    }

    public function args(): array
    {
        return [
            'id'   => ['type' => GType::nonNull(GType::id())],
            'name' => ['type' => GType::nonNull(GType::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100']];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $organization = Organization::query()->findOrFail($args['id']);
        $organization->update(['name' => $args['name']]);

        return $organization;
    }
}
