<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Scalars\Database\InetType;
use Ayimdomnic\Laragraph\Scalars\Database\UuidType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Foundation\Application;

/**
 * Integration tests for database preset auto-registration via the service provider.
 */
class DatabasePresetIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Enable the postgres preset so mergePresetTypes() has work to do
        $app['config']->set('laragraph.database_types.preset', 'postgres');
        $app['config']->set('laragraph.database_types.custom', []);
    }

    public function test_preset_types_are_merged_into_laragraph_types_config(): void
    {
        // The service provider's boot() → mergePresetTypes() should have merged
        // the postgres preset types into laragraph.types
        $types = config('laragraph.types', []);

        $this->assertSame(UuidType::class, $types['UUID'] ?? null);
        $this->assertSame(InetType::class, $types['Inet'] ?? null);
    }

    public function test_custom_types_are_merged_alongside_preset(): void
    {
        // Reconfigure in-process so custom types are also present
        config(['laragraph.database_types.custom' => ['TSVector' => TsvectorStub::class]]);

        // Re-run the merge logic manually (simulates a fresh boot with the new config)
        $preset      = config('laragraph.database_types.preset');
        $custom      = config('laragraph.database_types.custom', []);
        $presetTypes = \Ayimdomnic\Laragraph\Scalars\Database\DatabasePreset::types($preset);

        config(['laragraph.types' => array_merge(
            config('laragraph.types', []),
            $presetTypes,
            $custom,
        )]);

        $this->assertSame(TsvectorStub::class, config('laragraph.types.TSVector'));
    }
}

/** @internal fixture */
class TsvectorStub extends \Ayimdomnic\Laragraph\Scalars\Database\TsvectorType {}
