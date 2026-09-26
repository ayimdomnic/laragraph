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

        $this->assertFileExists(app_path('GraphQL/Types/Inputs/CreateArticleInput.php'));
    }

    // -------------------------------------------------------------------------
    // laragraph:make:exception
    // -------------------------------------------------------------------------

    public function test_make_exception_creates_file(): void
    {
        $this->artisan('laragraph:make:exception', ['name' => 'InvalidCredentialsException'])
             ->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Exceptions/InvalidCredentialsException.php'));
    }

    public function test_make_exception_file_uses_correct_namespace_and_derived_code_and_key(): void
    {
        $this->artisan('laragraph:make:exception', ['name' => 'InvalidCredentialsException']);
        $contents = file_get_contents(app_path('GraphQL/Exceptions/InvalidCredentialsException.php'));

        $this->assertStringContainsString('namespace App\\GraphQL\\Exceptions', $contents);
        $this->assertStringContainsString("'errors.invalid_credentials'", $contents);
        $this->assertStringContainsString("'INVALID_CREDENTIALS'", $contents);
    }

    public function test_make_exception_derives_key_and_code_without_a_trailing_exception_suffix(): void
    {
        $this->artisan('laragraph:make:exception', ['name' => 'OutOfStock']);
        $contents = file_get_contents(app_path('GraphQL/Exceptions/OutOfStock.php'));

        $this->assertStringContainsString("'errors.out_of_stock'", $contents);
        $this->assertStringContainsString("'OUT_OF_STOCK'", $contents);
    }
}
