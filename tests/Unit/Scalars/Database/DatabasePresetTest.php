<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\Database\BigIntType;
use Ayimdomnic\Laragraph\Scalars\Database\DatabasePreset;
use Ayimdomnic\Laragraph\Scalars\Database\InetType;
use Ayimdomnic\Laragraph\Scalars\Database\IntervalType;
use Ayimdomnic\Laragraph\Scalars\Database\JsonbType;
use Ayimdomnic\Laragraph\Scalars\Database\MoneyType;
use Ayimdomnic\Laragraph\Scalars\Database\TsvectorType;
use Ayimdomnic\Laragraph\Scalars\Database\UuidType;
use Ayimdomnic\Laragraph\Tests\TestCase;

class DatabasePresetTest extends TestCase
{
    // -------------------------------------------------------------------------
    // supported()
    // -------------------------------------------------------------------------

    public function test_supported_returns_all_preset_names(): void
    {
        $this->assertSame(
            ['postgres', 'cockroachdb', 'mssql', 'oracle'],
            DatabasePreset::supported(),
        );
    }

    // -------------------------------------------------------------------------
    // postgres preset
    // -------------------------------------------------------------------------

    public function test_postgres_preset_contains_all_types(): void
    {
        $types = DatabasePreset::types('postgres');

        $this->assertSame(UuidType::class,      $types['UUID']);
        $this->assertSame(BigIntType::class,    $types['BigInt']);
        $this->assertSame(JsonbType::class,     $types['JSONB']);
        $this->assertSame(MoneyType::class,     $types['Money']);
        $this->assertSame(TsvectorType::class,  $types['TSVector']);
        $this->assertSame(IntervalType::class,  $types['Interval']);
        $this->assertSame(InetType::class,      $types['Inet']);
        $this->assertCount(7, $types);
    }

    // -------------------------------------------------------------------------
    // cockroachdb preset
    // -------------------------------------------------------------------------

    public function test_cockroachdb_preset_contains_correct_types(): void
    {
        $types = DatabasePreset::types('cockroachdb');

        $this->assertSame(UuidType::class,   $types['UUID']);
        $this->assertSame(BigIntType::class, $types['BigInt']);
        $this->assertSame(JsonbType::class,  $types['JSONB']);
        $this->assertSame(InetType::class,   $types['Inet']);
        $this->assertCount(4, $types);
    }

    // -------------------------------------------------------------------------
    // mssql preset
    // -------------------------------------------------------------------------

    public function test_mssql_preset_contains_correct_types(): void
    {
        $types = DatabasePreset::types('mssql');

        $this->assertSame(UuidType::class,   $types['UUID']);
        $this->assertSame(BigIntType::class, $types['BigInt']);
        $this->assertSame(MoneyType::class,  $types['Money']);
        $this->assertCount(3, $types);
    }

    // -------------------------------------------------------------------------
    // oracle preset
    // -------------------------------------------------------------------------

    public function test_oracle_preset_contains_correct_types(): void
    {
        $types = DatabasePreset::types('oracle');

        $this->assertSame(UuidType::class,      $types['UUID']);
        $this->assertSame(BigIntType::class,    $types['BigInt']);
        $this->assertSame(IntervalType::class,  $types['Interval']);
        $this->assertCount(3, $types);
    }

    // -------------------------------------------------------------------------
    // unknown preset
    // -------------------------------------------------------------------------

    public function test_unknown_preset_returns_empty_array(): void
    {
        $this->assertSame([], DatabasePreset::types('unknown_db'));
    }

    // -------------------------------------------------------------------------
    // Case-insensitivity
    // -------------------------------------------------------------------------

    public function test_preset_name_is_case_insensitive(): void
    {
        $this->assertSame(
            DatabasePreset::types('postgres'),
            DatabasePreset::types('POSTGRES'),
        );
    }

    // -------------------------------------------------------------------------
    // ServiceProvider integration
    // -------------------------------------------------------------------------

    public function test_service_provider_merges_preset_types_into_config(): void
    {
        config(['laragraph.database_types.preset' => 'postgres']);
        config(['laragraph.database_types.custom' => []]);

        // Force re-merge by simulating what mergePresetTypes() does
        $preset      = config('laragraph.database_types.preset');
        $presetTypes = DatabasePreset::types($preset);

        config(['laragraph.types' => array_merge(
            config('laragraph.types', []),
            $presetTypes,
        )]);

        $this->assertSame(UuidType::class, config('laragraph.types.UUID'));
        $this->assertSame(InetType::class, config('laragraph.types.Inet'));
    }

    public function test_custom_types_are_merged_over_preset(): void
    {
        $customClass = TsvectorType::class; // repurpose for the test

        config(['laragraph.database_types.preset' => 'mssql']);
        config(['laragraph.database_types.custom' => ['TSVector' => $customClass]]);

        $preset = config('laragraph.database_types.preset');
        $custom = config('laragraph.database_types.custom');

        $merged = array_merge(
            config('laragraph.types', []),
            DatabasePreset::types($preset),
            $custom,
        );

        $this->assertSame($customClass, $merged['TSVector']);
    }
}
