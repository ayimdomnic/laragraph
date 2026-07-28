<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Console;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a clean app/GraphQL directory for each test
        File::deleteDirectory(app_path('GraphQL'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('GraphQL'));
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // laragraph:make:type
    // -------------------------------------------------------------------------

    public function test_make_type_creates_file(): void
    {
        $this->artisan('laragraph:make:type', ['name' => 'ArticleType'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Types/ArticleType.php'));
    }

    public function test_make_type_file_uses_correct_namespace(): void
    {
        $this->artisan('laragraph:make:type', ['name' => 'ArticleType']);
        $contents = file_get_contents(app_path('GraphQL/Types/ArticleType.php'));
        $this->assertStringContainsString('namespace App\\GraphQL\\Types', $contents);
    }

    // -------------------------------------------------------------------------
    // laragraph:make:query
    // -------------------------------------------------------------------------

    public function test_make_query_creates_file(): void
    {
        $this->artisan('laragraph:make:query', ['name' => 'ArticlesQuery'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Queries/ArticlesQuery.php'));
    }

    // -------------------------------------------------------------------------
    // laragraph:make:mutation
    // -------------------------------------------------------------------------

    public function test_make_mutation_creates_file(): void
    {
        $this->artisan('laragraph:make:mutation', ['name' => 'CreateArticleMutation'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Mutations/CreateArticleMutation.php'));
    }

    // -------------------------------------------------------------------------
    // laragraph:make:subscription
    // -------------------------------------------------------------------------

    public function test_make_subscription_creates_file(): void
    {
        $this->artisan('laragraph:make:subscription', ['name' => 'ArticleCreatedSubscription'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Subscriptions/ArticleCreatedSubscription.php'));
    }

    // -------------------------------------------------------------------------
    // laragraph:make:input
    // -------------------------------------------------------------------------

    public function test_make_input_creates_file(): void
    {
        $this->artisan('laragraph:make:input', ['name' => 'CreateArticleInput'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Inputs/CreateArticleInput.php'));
    }
}
