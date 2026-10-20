<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Auth\FieldVisibility;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class CsDoc extends Model
{
    protected $guarded = [];
}

/**
 * Call recorder for Gate abilities and canSee closures — proves how often a
 * check ran, against which subject, and with what arguments.
 */
class CsCounters
{
    public static bool $toggle = true;

    /** @var array<string, int> */
    public static array $abilityCalls = [];

    /** @var list<mixed> */
    public static array $seenSubjects = [];

    /** @var array{0: mixed, 1: array<string, mixed>, 2: mixed}|null */
    public static ?array $closureArgs = null;

    public static function hit(string $ability): void
    {
        self::$abilityCalls[$ability] = (self::$abilityCalls[$ability] ?? 0) + 1;
    }

    public static function calls(string $ability): int
    {
        return self::$abilityCalls[$ability] ?? 0;
    }

    public static function reset(): void
    {
        self::$toggle       = true;
        self::$abilityCalls = [];
        self::$seenSubjects = [];
        self::$closureArgs  = null;
    }
}

class CsSecretType extends Type
{
    protected array $attributes = ['name' => 'CsSecret'];

    public function fields(): array
    {
        return ['value' => GType::string()];
    }
}

class CsThingType extends Type
{
    protected array $attributes = ['name' => 'CsThing'];

    public function fields(): array
    {
        return [
            'id'    => GType::nonNull(GType::id()),
            'name'  => GType::string(),
            // String form — ability checked against the parent. Two fields,
            // one ability, one subject: the second must hit the per-request cache.
            'email' => ['type' => GType::string(), 'canSee' => 'viewEmail'],
            'phone' => ['type' => GType::string(), 'canSee' => 'viewEmail'],
            // Array form — the ability is checked against a *related* subject.
            'billing' => ['type' => GType::string(), 'canSee' => ['viewBilling', static fn(mixed $root): mixed => $root->billing_ref]],
            // Closure form — full control over the check.
            'note' => [
                'type'    => GType::string(),
                'canSee'  => static function (mixed $root, array $args, mixed $ctx): bool {
                    CsCounters::$closureArgs = [$root, $args, $ctx];

                    return (bool) $root->note_visible;
                },
                'resolve' => static fn(): string => 'memo',
            ],
            // Non-nullable object field — failure must propagate null per spec.
            'secret' => ['type' => GType::nonNull(Laragraph::type('CsSecret')), 'canSee' => 'viewSecret'],
            // List field — checked once against the parent, not per element.
            'tags' => ['type' => GType::listOf(GType::string()), 'canSee' => 'viewTags', 'resolve' => static fn(): array => ['a', 'b']],
            // Ability nobody defined — must fail closed with one warning per request.
            'redactedA' => ['type' => GType::string(), 'canSee' => 'totallyUndefined'],
            'redactedB' => ['type' => GType::string(), 'canSee' => 'totallyUndefined'],
            // guard() helper — declarative wrap of an explicit resolver.
            'guardedRaw' => ['type' => GType::string(), 'resolve' => $this->guard('viewGuarded', static fn(): string => 'raw-value')],
            // canSee() helper — imperative check inside an auto-attached resolver.
            'lower' => GType::string(),
        ];
    }

    protected function resolveLowerField(mixed $root, array $args, mixed $context): ?string
    {
        return $this->canSee('viewEmail', $root, $args, $context)
            ? strtolower((string) $root->email)
            : null;
    }
}

class CsThingQuery extends Query
{
    public static mixed $source = null;

    public function type(): GType
    {
        return Laragraph::type('CsThing');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return self::$source;
    }
}

class FieldCanSeeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.types', [
            'CsThing' => CsThingType::class,
            'CsSecret' => CsSecretType::class,
        ]);
        $app['config']->set('laragraph.schemas.default', ['query' => ['thing' => CsThingQuery::class]]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        CsCounters::reset();
        FieldVisibility::reset();

        $this->actingAs((new User())->forceFill(['id' => 1]));
    }

    private function thing(string $fields, string $query = '{thing}'): array
    {
        CsThingQuery::$source = (new CsDoc())->forceFill([
            'id'           => 7,
            'name'         => 'Ada',
            'email'        => 'ADA@EXAMPLE.COM',
            'phone'        => '123-456',
            'billing'      => 'OK-REF',
            'billing_ref'  => 'OK-REF',
            'note_visible' => true,
            'secret'       => ['value' => 'top'],
        ]);

        return $this->graphql(str_replace('{thing}', "{ thing { {$fields} } }", $query));
    }

    // -------------------------------------------------------------------------
    // String form
    // -------------------------------------------------------------------------

    public function test_a_field_whose_ability_passes_resolves_normally(): void
    {
        Gate::define('viewEmail', fn(Authenticatable $user): bool => $user->getAuthIdentifier() === 1);

        $result = $this->thing('email');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('ADA@EXAMPLE.COM', $result['data']['thing']['email']);
    }

    public function test_a_field_whose_ability_fails_is_redacted_without_any_error(): void
    {
        Gate::define('viewEmail', fn(Authenticatable $user): bool => false);

        $result = $this->thing('email');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($result['data']['thing']['email']);
    }

    public function test_the_ability_receives_the_parent_as_its_subject(): void
    {
        Gate::define('viewEmail', function (Authenticatable $user, $doc = null): bool {
            CsCounters::$seenSubjects[] = $doc;

            return true;
        });

        $this->thing('email');

        $this->assertInstanceOf(CsDoc::class, CsCounters::$seenSubjects[0] ?? null);
        $this->assertSame(7, CsCounters::$seenSubjects[0]->getKey());
    }

    public function test_a_guest_gets_null_rather_than_an_exception(): void
    {
        // Drop the actingAs() identity — the request is now unauthenticated.
        $this->app['auth']->forgetGuards();

        Gate::define('viewEmail', function (): bool {
            CsCounters::hit('viewEmail');

            return true;
        });

        $result = $this->thing('email');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($result['data']['thing']['email']);
        // The Gate must not even be consulted for a guest.
        $this->assertSame(0, CsCounters::calls('viewEmail'));
    }

    public function test_redaction_results_do_not_leak_between_requests(): void
    {
        Gate::define('viewEmail', fn(Authenticatable $user): bool => $user->getAuthIdentifier() === 1);

        $this->actingAs((new User())->forceFill(['id' => 2]));
        $this->assertNull($this->thing('email')['data']['thing']['email']);

        $this->actingAs((new User())->forceFill(['id' => 1]));
        $this->assertSame('ADA@EXAMPLE.COM', $this->thing('email')['data']['thing']['email']);
    }

    // -------------------------------------------------------------------------
    // Array and closure forms
    // -------------------------------------------------------------------------

    public function test_the_array_form_checks_the_ability_against_a_resolved_subject(): void
    {
        Gate::define('viewBilling', function (Authenticatable $user, $subject = null): bool {
            CsCounters::$seenSubjects[] = $subject;

            return $subject === 'OK-REF';
        });

        $this->assertSame('OK-REF', $this->thing('billing')['data']['thing']['billing']);

        CsCounters::reset();
        CsThingQuery::$source = (new CsDoc())->forceFill(['id' => 7, 'billing' => 'X', 'billing_ref' => 'OTHER']);

        $denied = $this->graphql('{ thing { billing } }');

        $this->assertArrayNotHasKey('errors', $denied);
        $this->assertNull($denied['data']['thing']['billing']);
        $this->assertSame(['OTHER'], CsCounters::$seenSubjects);
    }

    public function test_the_closure_form_receives_parent_args_and_context(): void
    {
        $this->assertSame('memo', $this->thing('note')['data']['thing']['note']);

        $this->assertIsArray(CsCounters::$closureArgs);
        [$root, $args, $ctx] = CsCounters::$closureArgs;
        $this->assertInstanceOf(CsDoc::class, $root);
        $this->assertSame([], $args);
        $this->assertInstanceOf(GraphQLContext::class, $ctx);

        CsThingQuery::$source = (new CsDoc())->forceFill(['id' => 7, 'note_visible' => false]);

        $denied = $this->graphql('{ thing { note } }');

        $this->assertArrayNotHasKey('errors', $denied);
        $this->assertNull($denied['data']['thing']['note']);
    }

    // -------------------------------------------------------------------------
    // Failure semantics
    // -------------------------------------------------------------------------

    public function test_a_denied_non_nullable_field_propagates_null_to_the_nearest_nullable_parent(): void
    {
        Gate::define('viewSecret', fn(Authenticatable $user): bool => false);

        $result = $this->thing('secret { value }');

        $this->assertNull($result['data']['thing']);

        // The failure is GraphQL's standard null-propagation error — never an
        // authorization error, and never a leak: it isn't client-safe, so the
        // public message stays generic even in debug mode.
        $this->assertStringNotContainsString('authorization', strtolower($result['errors'][0]['message']));
        $this->assertStringContainsString('Cannot return null for non-nullable field', $result['errors'][0]['extensions']['debugMessage']);

        // Visible when the ability passes.
        Gate::define('viewSecret', fn(Authenticatable $user): bool => true);
        $this->assertSame('top', $this->thing('secret { value }')['data']['thing']['secret']['value']);
    }

    public function test_an_unknown_ability_fails_closed_and_warns_once_per_request(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(\Mockery::pattern('/canSee ability \[totallyUndefined\]/'), ['ability' => 'totallyUndefined']);

        $result = $this->thing('redactedA redactedB');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($result['data']['thing']['redactedA']);
        $this->assertNull($result['data']['thing']['redactedB']);
    }

    public function test_introspection_still_lists_guarded_fields(): void
    {
        $result = $this->graphql('{ __type(name: "CsThing") { fields { name } } }');

        $names = array_column($result['data']['__type']['fields'], 'name');

        $this->assertContains('email', $names);
        $this->assertContains('phone', $names);
    }

    // -------------------------------------------------------------------------
    // Performance rules
    // -------------------------------------------------------------------------

    public function test_a_list_field_is_checked_once_against_the_parent_not_per_element(): void
    {
        Gate::define('viewTags', function (Authenticatable $user): bool {
            CsCounters::hit('viewTags');

            return true;
        });

        $result = $this->thing('tags');

        $this->assertSame(['a', 'b'], $result['data']['thing']['tags']);
        $this->assertSame(1, CsCounters::calls('viewTags'));
    }

    public function test_repeated_ability_checks_within_one_request_hit_the_cache(): void
    {
        Gate::define('viewEmail', function (Authenticatable $user): bool {
            CsCounters::hit('viewEmail');

            return true;
        });

        $result = $this->thing('email phone');

        $this->assertSame('ADA@EXAMPLE.COM', $result['data']['thing']['email']);
        $this->assertSame('123-456', $result['data']['thing']['phone']);
        // Two fields, same ability and subject — one Gate evaluation, then a cache hit.
        $this->assertSame(1, CsCounters::calls('viewEmail'));
    }

    // -------------------------------------------------------------------------
    // Base-class helpers
    // -------------------------------------------------------------------------

    public function test_the_guard_helper_wraps_an_explicit_resolver(): void
    {
        Gate::define('viewGuarded', fn(Authenticatable $user): bool => CsCounters::$toggle);

        CsCounters::$toggle = true;
        $this->assertSame('raw-value', $this->thing('guardedRaw')['data']['thing']['guardedRaw']);

        CsCounters::$toggle = false;
        $result = $this->thing('guardedRaw');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($result['data']['thing']['guardedRaw']);
    }

    public function test_the_can_see_helper_checks_a_rule_from_inside_a_resolver(): void
    {
        Gate::define('viewEmail', fn(Authenticatable $user): bool => CsCounters::$toggle);

        CsCounters::$toggle = true;
        $this->assertSame('ada@example.com', $this->thing('lower')['data']['thing']['lower']);

        CsCounters::$toggle = false;
        $result = $this->thing('lower');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($result['data']['thing']['lower']);
    }

    public function test_can_see_composes_with_tracing(): void
    {
        config(['laragraph.tracing.enabled' => true]);
        Gate::define('viewEmail', fn(Authenticatable $user): bool => false);

        $result = $this->thing('email');

        $this->assertNull($result['data']['thing']['email']);
        $this->assertArrayNotHasKey('errors', $result);
        $this->assertArrayHasKey('tracing', $result['extensions']);
    }
}
