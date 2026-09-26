<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Scalars\DateTimeType;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class DiffPingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

/** Adding a case is a "dangerous", non-breaking change. */
enum DiffColor
{
    case Red;
    case Green;
    case Blue;
}

class DiffColorQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('Color');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return DiffColor::Red;
    }
}

class DiffNowQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('DateTime');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return now();
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SchemaDiffCommandTest extends TestCase
{
    private string $baselinePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baselinePath = sys_get_temp_dir() . '/laragraph_diff_test_' . uniqid() . '.graphql';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->baselinePath)) {
            unlink($this->baselinePath);
        }
        parent::tearDown();
    }

    private function writeBaseline(string $sdl): void
    {
        file_put_contents($this->baselinePath, $sdl);
    }

    // -------------------------------------------------------------------------
    // No baseline yet
    // -------------------------------------------------------------------------

    public function test_succeeds_when_no_baseline_file_exists_yet(): void
    {
        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertSuccessful()
             ->expectsOutputToContain('nothing to compare against');
    }

    public function test_fails_when_against_option_is_missing(): void
    {
        $this->artisan('laragraph:schema:diff')->assertFailed();
    }

    // -------------------------------------------------------------------------
    // No changes
    // -------------------------------------------------------------------------

    public function test_succeeds_with_no_changes(): void
    {
        config(['laragraph.schemas.default' => ['query' => ['diffPing' => DiffPingQuery::class]]]);

        $this->writeBaseline("type Query {\n  diffPing: String\n}\n");

        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertSuccessful()
             ->expectsOutputToContain('No breaking or dangerous changes');
    }

    // -------------------------------------------------------------------------
    // Breaking change: a field removed
    // -------------------------------------------------------------------------

    public function test_fails_when_a_field_was_removed(): void
    {
        config(['laragraph.schemas.default' => ['query' => ['diffPing' => DiffPingQuery::class]]]);

        $this->writeBaseline("type Query {\n  diffPing: String\n  removedField: String\n}\n");

        // Only one substring is asserted per line: Laravel's expectsOutputToContain()
        // mock matches at most one expectation per actual write, so two substrings
        // that both land in the same rendered line can't both be verified here.
        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertFailed()
             ->expectsOutputToContain('FIELD_REMOVED');
    }

    // -------------------------------------------------------------------------
    // Dangerous-only change: a value added to an enum
    // -------------------------------------------------------------------------

    private function configureColorSchema(): void
    {
        config([
            'laragraph.types' => ['Color' => DiffColor::class],
            'laragraph.schemas.default' => [
                'query' => ['diffPing' => DiffPingQuery::class, 'color' => DiffColorQuery::class],
            ],
        ]);
    }

    public function test_dangerous_changes_do_not_fail_by_default(): void
    {
        $this->configureColorSchema();

        $this->writeBaseline("type Query {\n  diffPing: String\n  color: Color\n}\n\nenum Color {\n  Red\n  Green\n}\n");

        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertSuccessful()
             ->expectsOutputToContain('DANGEROUS');
    }

    public function test_fail_on_dangerous_flag_fails_the_command(): void
    {
        $this->configureColorSchema();

        $this->writeBaseline("type Query {\n  diffPing: String\n  color: Color\n}\n\nenum Color {\n  Red\n  Green\n}\n");

        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath, '--fail-on-dangerous' => true])
             ->assertFailed()
             ->expectsOutputToContain('DANGEROUS');
    }

    // -------------------------------------------------------------------------
    // Unparseable baseline
    // -------------------------------------------------------------------------

    public function test_fails_when_the_baseline_is_not_valid_sdl(): void
    {
        config(['laragraph.schemas.default' => ['query' => ['diffPing' => DiffPingQuery::class]]]);

        $this->writeBaseline('not { valid sdl @@@');

        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // A custom scalar's own PHP class must not look like a "kind change"
    // -------------------------------------------------------------------------

    /**
     * BuildSchema::build() parses `scalar DateTime` as a generic
     * CustomScalarType, unrelated (by `instanceof`) to the real
     * Ayimdomnic\Laragraph\Scalars\DateTimeType. Diffing the live PHP schema
     * directly against an SDL-parsed baseline would misreport every custom
     * scalar as TYPE_CHANGED_KIND on every run, even with zero real changes —
     * the command must round-trip the current schema through SDL too.
     */
    public function test_a_custom_scalars_php_class_is_not_reported_as_a_breaking_change(): void
    {
        config([
            'laragraph.types' => ['DateTime' => DateTimeType::class],
            'laragraph.schemas.default' => [
                'query' => ['diffPing' => DiffPingQuery::class, 'now' => DiffNowQuery::class],
            ],
        ]);

        $this->writeBaseline("type Query {\n  diffPing: String\n  now: DateTime\n}\n\nscalar DateTime\n");

        $this->artisan('laragraph:schema:diff', ['--against' => $this->baselinePath])
             ->assertSuccessful()
             ->expectsOutputToContain('No breaking or dangerous changes');
    }
}
