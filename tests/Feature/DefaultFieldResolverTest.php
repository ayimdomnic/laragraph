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
use Illuminate\Support\Facades\Log;

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
            'age'      => GType::int(),
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

    // -------------------------------------------------------------------------
    // Unresolved-field warning (config('app.debug') is true in every package
    // test — see tests/TestCase.php — so it's on by default here unless a
    // test overrides laragraph.log_unresolved_fields explicitly).
    // -------------------------------------------------------------------------

    public function test_warns_when_an_eloquent_model_has_no_matching_attribute(): void
    {
        Log::shouldReceive('warning')->once()->with(
            \Mockery::pattern('/field \[missing\] on \[DfrThing\]/'),
            ['field' => 'missing', 'type' => 'DfrThing'],
        );

        $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'missing');
    }

    public function test_does_not_warn_for_a_genuinely_null_column_value(): void
    {
        Log::shouldReceive('warning')->never();

        $result = $this->thing((new DfrModel())->forceFill(['name' => 'ada', 'age' => null]), 'age');

        $this->assertSame(['age' => null], $result);
    }

    public function test_does_not_warn_when_an_accessor_resolves_the_field(): void
    {
        Log::shouldReceive('warning')->never();

        $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'shout');
    }

    public function test_warns_when_an_array_source_has_no_matching_key(): void
    {
        Log::shouldReceive('warning')->once()->with(
            \Mockery::pattern('/field \[missing\] on \[DfrThing\]/'),
            \Mockery::any(),
        );

        $this->thing(['name' => 'ada'], 'missing');
    }

    public function test_does_not_warn_for_a_genuinely_null_array_value(): void
    {
        Log::shouldReceive('warning')->never();

        $this->thing(['name' => 'ada', 'age' => null], 'age');
    }

    public function test_does_not_warn_when_explicitly_disabled_even_in_debug_mode(): void
    {
        config(['laragraph.log_unresolved_fields' => false]);
        Log::shouldReceive('warning')->never();

        $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'missing');
    }

    public function test_warns_when_explicitly_enabled_outside_debug_mode(): void
    {
        config(['app.debug' => false, 'laragraph.log_unresolved_fields' => true]);
        Log::shouldReceive('warning')->once();

        $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'missing');
    }

    public function test_does_not_warn_outside_debug_mode_by_default(): void
    {
        config(['app.debug' => false]);
        Log::shouldReceive('warning')->never();

        $this->thing((new DfrModel())->forceFill(['name' => 'ada']), 'missing');
    }
}
