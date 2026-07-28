<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class RePingQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'pong'; }
}

class CustomExtension implements GraphQLExtensionInterface
{
    public function key(): string { return 'custom'; }
    public function get(array $context = []): array { return ['hello' => 'world']; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ResponseExtensionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laragraph.schemas.default', [
            'query' => ['rePing' => RePingQuery::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // No extensions
    // -------------------------------------------------------------------------

    public function test_response_has_no_extensions_key_when_disabled(): void
    {
        $result = $this->graphql('{ rePing }');

        $this->assertArrayNotHasKey('extensions', $result);
    }

    // -------------------------------------------------------------------------
    // request_id extension
    // -------------------------------------------------------------------------

    public function test_request_id_appears_in_response_when_enabled(): void
    {
        $this->app['config']->set('laragraph.extensions.request_id', true);

        $result = $this->graphql('{ rePing }');

        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('requestId', $result['extensions']);
        $this->assertArrayHasKey('id', $result['extensions']['requestId']);
        $this->assertNotEmpty($result['extensions']['requestId']['id']);
    }

    public function test_request_id_uses_incoming_x_request_id_header(): void
    {
        $this->app['config']->set('laragraph.extensions.request_id', true);

        $result = $this->postJson(
            '/graphql',
            ['query' => '{ rePing }'],
            ['X-Request-ID' => 'trace-abc-123']
        )->json();

        $this->assertSame('trace-abc-123', $result['extensions']['requestId']['id'] ?? null);
    }

    public function test_request_id_is_auto_generated_uuid_when_no_header(): void
    {
        $this->app['config']->set('laragraph.extensions.request_id', true);

        $result = $this->graphql('{ rePing }');
        $id     = $result['extensions']['requestId']['id'] ?? null;

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $id
        );
    }

    // -------------------------------------------------------------------------
    // query_timing extension
    // -------------------------------------------------------------------------

    public function test_query_timing_appears_in_response_when_enabled(): void
    {
        $this->app['config']->set('laragraph.extensions.query_timing', true);

        $result = $this->graphql('{ rePing }');

        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('timing', $result['extensions']);
        $this->assertArrayHasKey('execution_ms', $result['extensions']['timing']);
    }

    public function test_query_timing_value_is_a_non_negative_number(): void
    {
        $this->app['config']->set('laragraph.extensions.query_timing', true);

        $result = $this->graphql('{ rePing }');
        $ms     = $result['extensions']['timing']['execution_ms'] ?? -1;

        $this->assertGreaterThanOrEqual(0, $ms);
    }

    // -------------------------------------------------------------------------
    // Both built-ins together
    // -------------------------------------------------------------------------

    public function test_both_built_in_extensions_appear_together(): void
    {
        $this->app['config']->set('laragraph.extensions.request_id', true);
        $this->app['config']->set('laragraph.extensions.query_timing', true);

        $result = $this->graphql('{ rePing }');

        $this->assertArrayHasKey('requestId', $result['extensions'] ?? []);
        $this->assertArrayHasKey('timing', $result['extensions'] ?? []);
    }

    // -------------------------------------------------------------------------
    // Custom user-registered extension
    // -------------------------------------------------------------------------

    public function test_custom_extension_added_to_registry_appears_in_response(): void
    {
        $this->app->make(ExtensionRegistry::class)->add(new CustomExtension());

        $result = $this->graphql('{ rePing }');

        $this->assertSame(['hello' => 'world'], $result['extensions']['custom'] ?? null);
    }

    public function test_custom_extension_context_receives_execution_ms(): void
    {
        $contextCapture = new class implements GraphQLExtensionInterface {
            public array $capturedContext = [];
            public function key(): string { return 'capture'; }
            public function get(array $context = []): array
            {
                $this->capturedContext = $context;
                return ['ms' => $context['execution_ms'] ?? null];
            }
        };

        $this->app->make(ExtensionRegistry::class)->add($contextCapture);

        $this->graphql('{ rePing }');

        $this->assertArrayHasKey('execution_ms', $contextCapture->capturedContext);
        $this->assertIsFloat($contextCapture->capturedContext['execution_ms']);
    }
}
