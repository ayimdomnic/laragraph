<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature\Relay;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Relay\GlobalId;
use Ayimdomnic\Laragraph\Relay\NodeQuery;
use Ayimdomnic\Laragraph\Support\InterfaceType;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class NodeFixture extends InterfaceType
{
    protected array $attributes = ['name' => 'Node'];

    public function fields(): array
    {
        return ['id' => GType::nonNull(GType::id())];
    }

    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return match (true) {
            $value instanceof WidgetFixture => Laragraph::type('Widget'),
            default => null,
        };
    }
}

/** A plain value object standing in for an Eloquent model. */
class WidgetFixture
{
    public function __construct(public string $id, public string $label) {}
}

class WidgetType extends Type
{
    protected array $attributes = ['name' => 'Widget'];

    public function __construct()
    {
        $this->attributes['interfaces'] = fn(): array => [Laragraph::type('Node')];
        parent::__construct();
    }

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'label' => GType::nonNull(GType::string()),
        ];
    }

    /** @var array<string, WidgetFixture> */
    public static array $records = [];

    public function resolveNode(string $id, mixed $context): ?object
    {
        return self::$records[$id] ?? null;
    }
}

/** Implements Node but never overrides resolveNode() — should stay un-refetchable. */
class GadgetType extends Type
{
    protected array $attributes = ['name' => 'Gadget'];

    public function __construct()
    {
        $this->attributes['interfaces'] = fn(): array => [Laragraph::type('Node')];
        parent::__construct();
    }

    public function fields(): array
    {
        return ['id' => GType::nonNull(GType::id())];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class NodeQueryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        WidgetType::$records = ['1' => new WidgetFixture('1', 'First widget')];

        $app['config']->set('laragraph.types', [
            'Node'   => NodeFixture::class,
            'Widget' => WidgetType::class,
            'Gadget' => GadgetType::class,
        ]);
        $app['config']->set('laragraph.schemas.default.query', ['node' => NodeQuery::class]);
    }

    public function test_node_resolves_a_known_type_and_id(): void
    {
        $globalId = GlobalId::encode('Widget', '1');

        $response = $this->graphql('query ($id: ID!) { node(id: $id) { id ... on Widget { label } } }', ['id' => $globalId]);

        // Laragraph doesn't force a type's own `id` field to emit the global id —
        // that's each type's choice (see docs/06-pagination.md) — so this fixture's
        // `id` field still returns the raw underlying id, not $globalId.
        $this->assertSame(['id' => '1', 'label' => 'First widget'], $response['data']['node']);
    }

    public function test_node_returns_null_for_an_unknown_id_on_a_known_type(): void
    {
        $globalId = GlobalId::encode('Widget', '999');

        $response = $this->graphql('query ($id: ID!) { node(id: $id) { id } }', ['id' => $globalId]);

        $this->assertNull($response['data']['node']);
        $this->assertArrayNotHasKey('errors', $response);
    }

    public function test_node_returns_null_for_a_type_that_never_overrode_resolve_node(): void
    {
        $globalId = GlobalId::encode('Gadget', '1');

        $response = $this->graphql('query ($id: ID!) { node(id: $id) { id } }', ['id' => $globalId]);

        $this->assertNull($response['data']['node']);
    }

    public function test_node_returns_null_for_an_unregistered_type_name(): void
    {
        $globalId = GlobalId::encode('DoesNotExist', '1');

        $response = $this->graphql('query ($id: ID!) { node(id: $id) { id } }', ['id' => $globalId]);

        $this->assertNull($response['data']['node']);
    }

    public function test_node_returns_null_for_a_malformed_global_id(): void
    {
        $response = $this->graphql('query ($id: ID!) { node(id: $id) { id } }', ['id' => 'not-a-global-id']);

        $this->assertNull($response['data']['node']);
    }
}
