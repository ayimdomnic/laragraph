<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Support;

use Ayimdomnic\Laragraph\Support\FillableResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Fixtures — models declaring fillable columns via property, attribute, both,
// neither, inherited attribute and trait attribute.
// ---------------------------------------------------------------------------

class FrPropertyOnly extends Model
{
    protected $fillable = ['name', 'email'];
}

#[Fillable(['title', 'body'])]
class FrAttributeOnly extends Model {}

#[Fillable(['from_attribute'])]
class FrBothDeclared extends Model
{
    protected $fillable = ['from_property'];
}

class FrNothingDeclared extends Model {}

/** Inherits the #[Fillable] attribute from FrAttributeOnly. */
class FrAttributeChild extends FrAttributeOnly {}

#[Fillable(['from_trait'])]
trait FrFillableTrait {}

class FrTraitHost extends Model
{
    use FrFillableTrait;
}

/** Mutates $fillable at runtime — the resolver must only see declared intent. */
class FrConstructorMutates extends Model
{
    protected $fillable = ['declared'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->fillable[] = 'runtime_added';
    }
}

class FrNotAModel {}

/** Sparse keys in the declaration must not survive into the result. */
class FrKeyedFillable extends Model
{
    protected $fillable = [3 => 'name', 7 => 'email'];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class FillableResolverTest extends TestCase
{
    /** Attribute-based mass assignment only exists on Laravel 11+. */
    private function requireFillableAttribute(): void
    {
        if (! class_exists(Fillable::class)) {
            $this->markTestSkipped('#[Fillable] requires Laravel 11+.');
        }
    }

    public function test_property_declaration_is_returned(): void
    {
        $this->assertSame(['name', 'email'], FillableResolver::for(FrPropertyOnly::class));
    }

    public function test_attribute_declaration_is_used_when_no_property(): void
    {
        $this->requireFillableAttribute();

        $this->assertSame(['title', 'body'], FillableResolver::for(FrAttributeOnly::class));
    }

    public function test_property_wins_when_both_are_declared(): void
    {
        $this->requireFillableAttribute();

        $this->assertSame(['from_property'], FillableResolver::for(FrBothDeclared::class));
    }

    public function test_model_declaring_neither_returns_empty_list(): void
    {
        $this->assertSame([], FillableResolver::for(FrNothingDeclared::class));
    }

    public function test_attribute_is_inherited_from_parent_class(): void
    {
        $this->requireFillableAttribute();

        $this->assertSame(['title', 'body'], FillableResolver::for(FrAttributeChild::class));
    }

    public function test_attribute_declared_on_a_trait_is_found(): void
    {
        $this->requireFillableAttribute();

        $this->assertSame(['from_trait'], FillableResolver::for(FrTraitHost::class));
    }

    public function test_runtime_fillable_mutation_is_ignored(): void
    {
        // Only the *declared* property is read — constructor-time additions
        // never leak into scaffold introspection (and never trigger the
        // framework's attribute merge either).
        $this->assertSame(['declared'], FillableResolver::for(FrConstructorMutates::class));
    }

    public function test_non_model_class_returns_empty_list(): void
    {
        $this->assertSame([], FillableResolver::for(FrNotAModel::class));
        $this->assertSame([], FillableResolver::for(\stdClass::class));
    }

    public function test_unknown_class_returns_empty_list(): void
    {
        $this->assertSame([], FillableResolver::for('Nope\\Missing\\Model'));
    }

    public function test_result_is_always_a_reindexed_list(): void
    {
        $this->assertSame(['name', 'email'], FillableResolver::for(FrKeyedFillable::class));
    }
}
