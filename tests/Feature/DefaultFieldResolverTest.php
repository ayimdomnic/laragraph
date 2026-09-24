<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Database\Eloquent\Model;

class DfrModel extends Model
{
    public static int $accessorCalls = 0;

    protected $guarded = [];

    public function getShoutAttribute(): string
    {
        self::$accessorCalls++;

        return strtoupper((string) $this->attributes['name']);
    }
}

class DfrThingType extends Type
{
    protected array $attributes = ['name' => 'DfrThing'];

    public function fields(): array
    {
        return [
            'name'     => GType::string(),
            'shout'    => GType::string(),
            'missing'  => GType::string(),
            'computed' => GType::string(),
        ];
    }
}

class DfrThingsQuery extends Query
{
    public static mixed $source = null;

    public function type(): GType
    {
        return Laragraph::type('DfrThing');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return self::$source;
    }
}

class DefaultFieldResolverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.types', ['DfrThing' => DfrThingType::class]);
        $app['config']->set('laragraph.schemas.default', ['query' => ['thing' => DfrThingsQuery::class]]);
    }

    protected function tearDown(): void
    {
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    private function thing(mixed $source, string $fields = 'name'): mixed
    {
        DfrThingsQuery::$source = $source;

        return Laragraph::execute("{ thing { {$fields} } }")['data']['thing'];
    }

    public function test_eloquent_attributes_are_computed_once_per_field(): void
    {
        DfrModel::$accessorCalls = 0;

        $this->assertSame(['shout' => 'ADA'], $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'shout'));
        $this->assertSame(1, DfrModel::$accessorCalls);
    }

    public function test_missing_attributes_read_as_null_in_strict_mode(): void
    {
        Model::preventAccessingMissingAttributes();

        // Laravel only throws MissingAttributeException for models loaded from the database.
        $model = (new DfrModel())->newFromBuilder(['name' => 'ada']);

        $this->assertSame(['name' => 'ada', 'missing' => null], $this->thing($model, 'name missing'));
    }

    public function test_arrays_array_access_and_objects_resolve_like_webonyx(): void
    {
        $this->assertSame(['name' => 'array'], $this->thing(['name' => 'array']));
        $this->assertSame(['name' => 'object'], $this->thing((object) ['name' => 'object']));
        $this->assertSame(['name' => 'offset'], $this->thing(new \ArrayObject(['name' => 'offset'])));
        $this->assertSame(['missing' => null], $this->thing(['name' => 'x'], 'missing'));
    }

    public function test_closure_values_are_called_with_the_resolver_arguments(): void
    {
        $result = $this->thing([
            'computed' => fn(array $source, array $args, mixed $context, ResolveInfo $info): string => "{$info->fieldName} of {$source['name']}",
            'name'     => 'ada',
        ], 'computed');

        $this->assertSame(['computed' => 'computed of ada'], $result);
    }

    public function test_tracing_wraps_the_default_resolver(): void
    {
        config(['laragraph.tracing.enabled' => true]);
        DfrThingsQuery::$source = ['name' => 'traced'];

        $result = Laragraph::execute('{ thing { name } }');

        $this->assertSame(['thing' => ['name' => 'traced']], $result['data']);
        $this->assertContains('name', array_column($result['extensions']['tracing']['execution']['resolvers'], 'fieldName'));
    }
}
