<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\File;

class GeneratedInputDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('GraphQL'));

        parent::tearDown();
    }

    public function test_generated_input_types_are_auto_discovered(): void
    {
        $this->artisan('laragraph:make:input', ['name' => 'CreateWidgetInput'])->assertSuccessful();

        require_once app_path('GraphQL/Types/Inputs/CreateWidgetInput.php');

        $this->assertSame(
            'App\\GraphQL\\Types\\Inputs\\CreateWidgetInput',
            Discover::types('app/GraphQL/Types')['CreateWidgetInput'] ?? null,
        );
    }
}
