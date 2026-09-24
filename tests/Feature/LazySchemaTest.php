<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\ScalarType;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

// ---------------------------------------------------------------------------
// Fixtures — every constructor records itself, so tests can tell which
// classes a request actually built.
// ---------------------------------------------------------------------------

final class LazyLog
{
    /** @var list<string> */
    public static array $built = [];
}

class LazyAuthorType extends Type
{
    protected array $attributes = ['name' => 'LazyAuthor'];

    public function __construct()
    {
        LazyLog::$built[] = 'LazyAuthor';
        parent::__construct();
    }

    public function fields(): array
    {
        return [
            'name' => GType::string(),
            'book' => Laragraph::type('LazyBook'),
        ];
    }
}

class LazyBookType extends Type
{
    protected array $attributes = ['name' => 'LazyBook'];

    public function __construct()
    {
        LazyLog::$built[] = 'LazyBook';
        parent::__construct();
    }

    public function fields(): array
    {
        return ['title' => GType::string()];
    }
}

class LazyUnusedType extends Type
{
    protected array $attributes = ['name' => 'LazyUnused'];

    public function __construct()
    {
        LazyLog::$built[] = 'LazyUnused';
        parent::__construct();
    }

    public function fields(): array
    {
        return ['nothing' => GType::string()];
    }
}

/** Registered under an alias that differs from its GraphQL name. */
class LazyAliasedType extends Type
{
    protected array $attributes = ['name' => 'LazyRenamed'];

    public function fields(): array
    {
        return ['ok' => GType::boolean()];
    }
}

/** Replaces the built-in ID scalar. */
class LazyIdType extends ScalarType
{
    public string $name = 'ID';

    public function serialize(mixed $value): string
    {
        return 'id:' . $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return $valueNode->value ?? null;
    }
}

class LazyAuthorQuery extends Query
{
    public function __construct()
    {
        LazyLog::$built[] = 'authorQuery';
    }

    public function type(): GType
    {
        return Laragraph::type('LazyAuthor');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['name' => 'Ada', 'book' => ['title' => 'Notes']];
    }
}

class LazyUnusedQuery extends Query
{
    public function __construct()
    {
        LazyLog::$built[] = 'unusedQuery';
    }

    public function type(): GType
    {
        return Laragraph::type('LazyUnused');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

class LazyIdQuery extends Query
{
    public function type(): GType
    {
        return GType::id();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 7;
    }
}

class LazyAliasedQuery extends Query
{
    public function type(): GType
    {
        return Laragraph::type('RenamedAlias');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['ok' => true];
    }
}

class LazyConnectionQuery extends Query
{
    public function type(): GType
    {
        return ConnectionType::make('LazyBookConnection', Laragraph::type('LazyBook'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['edges' => [['node' => ['title' => 'Notes'], 'cursor' => 'c1']], 'pageInfo' => [
            'hasNextPage' => false, 'hasPreviousPage' => false, 'startCursor' => 'c1', 'endCursor' => 'c1', 'total' => 1,
        ]];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class LazySchemaTest extends TestCase
{
    protected function setUp(): void
    {
        LazyLog::$built = [];

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.types', [
            'LazyAuthor'   => LazyAuthorType::class,
            'LazyBook'     => LazyBookType::class,
            'LazyUnused'   => LazyUnusedType::class,
            'RenamedAlias' => LazyAliasedType::class,
            'ID'           => LazyIdType::class,
        ]);
        $app['config']->set('laragraph.schemas.default', ['query' => [
            'author'     => LazyAuthorQuery::class,
            'unused'     => LazyUnusedQuery::class,
            'someId'     => LazyIdQuery::class,
            'aliased'    => LazyAliasedQuery::class,
            'connection' => LazyConnectionQuery::class,
        ]]);
    }

    public function test_building_the_schema_instantiates_no_fields_or_object_types(): void
    {
        Laragraph::schema();

        $this->assertSame([], LazyLog::$built);
    }

    public function test_a_request_builds_only_the_types_and_fields_it_uses(): void
    {
        $result = Laragraph::execute('{ author { name book { title } } }');

        $this->assertSame(['author' => ['name' => 'Ada', 'book' => ['title' => 'Notes']]], $result['data']);
        $this->assertEqualsCanonicalizing(['authorQuery', 'LazyAuthor', 'LazyBook'], LazyLog::$built);
    }

    public function test_registered_scalar_overrides_still_replace_the_built_in_scalars(): void
    {
        $this->assertSame(['someId' => 'id:7'], Laragraph::execute('{ someId }')['data']);
        $this->assertNotContains('LazyUnused', LazyLog::$built);
    }

    public function test_types_resolve_by_name_when_registered_under_another_alias(): void
    {
        $result = Laragraph::execute('{ aliased { ...F } } fragment F on LazyRenamed { ok }');

        $this->assertSame(['aliased' => ['ok' => true]], $result['data']);
    }

    public function test_unregistered_types_in_the_schema_resolve_by_name(): void
    {
        $result = Laragraph::execute('{ connection { ...C } } fragment C on LazyBookConnection { edges { node { title } } }');

        $this->assertSame(['connection' => ['edges' => [['node' => ['title' => 'Notes']]]]], $result['data']);
    }

    public function test_unknown_type_names_are_still_reported(): void
    {
        $result = Laragraph::execute('{ author { ...F } } fragment F on Nope { name }');

        $this->assertStringContainsString('Unknown type "Nope"', $result['errors'][0]['message']);
    }

    public function test_introspection_still_lists_every_type_and_field(): void
    {
        $result = Laragraph::execute('{ __schema { types { name } queryType { fields { name } } } }');

        $types = array_column($result['data']['__schema']['types'], 'name');

        foreach (['LazyAuthor', 'LazyBook', 'LazyUnused', 'LazyRenamed', 'LazyBookConnection', 'PageInfo'] as $name) {
            $this->assertContains($name, $types);
        }

        $this->assertEqualsCanonicalizing(
            ['author', 'unused', 'someId', 'aliased', 'connection'],
            array_column($result['data']['__schema']['queryType']['fields'], 'name'),
        );
    }

    public function test_validating_the_schema_builds_everything(): void
    {
        Laragraph::schema()->assertValid();

        $this->assertContains('unusedQuery', LazyLog::$built);
        $this->assertContains('LazyUnused', LazyLog::$built);
    }
}
