<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Support;

use Ayimdomnic\Laragraph\Support\EnumType;
use Ayimdomnic\Laragraph\Support\InputType;
use Ayimdomnic\Laragraph\Support\InterfaceType;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Support\UnionType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GType;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class StatusEnum extends EnumType
{
    protected array $attributes = ['name' => 'Status', 'description' => 'Account status.'];
    public function values(): array
    {
        return [
            'ACTIVE'   => ['value' => 'active'],
            'INACTIVE' => ['value' => 'inactive'],
        ];
    }
}

class CreatePostInput extends InputType
{
    protected array $attributes = ['name' => 'CreatePostInput', 'description' => 'Create post input.'];
    public function fields(): array
    {
        return [
            'title' => ['type' => GType::nonNull(GType::string())],
            'body'  => ['type' => GType::string()],
        ];
    }
}

class NodeInterface extends InterfaceType
{
    protected array $attributes = ['name' => 'Node', 'description' => 'Global ID node.'];
    public function fields(): array { return ['id' => ['type' => GType::nonNull(GType::id())]]; }
    public function resolveType(mixed $value, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed { return null; }
}

/** Subclass that relies on the *default* (non-overridden) resolveType. */
class NodeInterfaceDefault extends InterfaceType
{
    protected array $attributes = ['name' => 'NodeDefault'];
    public function fields(): array { return ['id' => ['type' => GType::nonNull(GType::id())]]; }
}

class ArticleType extends Type
{
    protected array $attributes = ['name' => 'Article'];
    public function fields(): array { return ['id' => ['type' => GType::id()]]; }
}

class VideoType extends Type
{
    protected array $attributes = ['name' => 'Video'];
    public function fields(): array { return ['id' => ['type' => GType::id()]]; }
}

class MediaUnion extends UnionType
{
    protected array $attributes = ['name' => 'Media', 'description' => 'Article or video.'];
    public function types(): array
    {
        return [new ArticleType(), new VideoType()];
    }
    public function resolveType(mixed $value, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed { return null; }
}

/** Subclass that relies on the *default* (non-overridden) resolveType. */
class MediaUnionDefault extends UnionType
{
    protected array $attributes = ['name' => 'MediaDefault'];
    public function types(): array { return [new ArticleType(), new VideoType()]; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class TypeSystemTest extends TestCase
{
    // EnumType
    public function test_enum_type_has_correct_name(): void
    {
        $enum = new StatusEnum();
        $this->assertSame('Status', $enum->name);
    }

    public function test_enum_type_has_values(): void
    {
        $enum   = new StatusEnum();
        $values = $enum->getValues();
        $this->assertCount(2, $values);
        $this->assertSame('active', $values[0]->value);
    }

    // InputType
    public function test_input_type_has_correct_name(): void
    {
        $input = new CreatePostInput();
        $this->assertSame('CreatePostInput', $input->name);
    }

    public function test_input_type_has_fields(): void
    {
        $input  = new CreatePostInput();
        $fields = $input->getFields();
        $this->assertArrayHasKey('title', $fields);
        $this->assertArrayHasKey('body', $fields);
    }

    // InterfaceType
    public function test_interface_type_has_correct_name(): void
    {
        $iface = new NodeInterface();
        $this->assertSame('Node', $iface->name);
    }

    public function test_interface_type_fields_closure_is_invoked(): void
    {
        $iface  = new NodeInterfaceDefault();
        $fields = $iface->getFields();
        $this->assertArrayHasKey('id', $fields);
    }

    public function test_interface_type_resolve_type_returns_null_by_default(): void
    {
        $iface = new NodeInterface();
        $this->assertNull($iface->resolveType(new \stdClass(), null, $this->createMock(\GraphQL\Type\Definition\ResolveInfo::class)));
    }

    public function test_interface_type_parent_resolve_type_returns_null(): void
    {
        $iface = new NodeInterfaceDefault();
        $this->assertNull($iface->resolveType(new \stdClass(), null, $this->createMock(\GraphQL\Type\Definition\ResolveInfo::class)));
    }

    // UnionType
    public function test_union_type_has_correct_name(): void
    {
        $union = new MediaUnion();
        $this->assertSame('Media', $union->name);
    }

    public function test_union_type_resolve_type_returns_null_by_default(): void
    {
        $union = new MediaUnion();
        $this->assertNull($union->resolveType(new \stdClass(), null, $this->createMock(\GraphQL\Type\Definition\ResolveInfo::class)));
    }

    public function test_union_type_parent_resolve_type_returns_null(): void
    {
        $union = new MediaUnionDefault();
        $this->assertNull($union->resolveType(new \stdClass(), null, $this->createMock(\GraphQL\Type\Definition\ResolveInfo::class)));
    }

    // Type — shorthand field registration
    public function test_type_shorthand_field_as_type_instance(): void
    {
        $typeClass = new class extends Type {
            protected array $attributes = ['name' => 'Shorthand'];
            public function fields(): array { return ['title' => GType::string()]; }
        };

        $fields = $typeClass->getFields();
        $this->assertArrayHasKey('title', $fields);
    }

    // Type — per-field resolve method
    public function test_type_per_field_resolve_method_is_attached(): void
    {
        $typeClass = new class extends Type {
            protected array $attributes = ['name' => 'WithResolver'];
            public function fields(): array { return ['name' => ['type' => GType::string()]]; }
            protected function resolveNameField(mixed $root, array $args): string { return 'resolved'; }
        };

        $fields = $typeClass->getFields();
        $this->assertNotNull($fields['name']->resolveFn);
    }
}
