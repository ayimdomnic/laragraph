<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Extensions;

use Ayimdomnic\Laragraph\Extensions\RequestIdExtension;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;

class RequestIdExtensionTest extends TestCase
{
    public function test_key_returns_request_id(): void
    {
        $this->assertSame('requestId', (new RequestIdExtension())->key());
    }

    public function test_get_returns_array_with_id_key(): void
    {
        $result = (new RequestIdExtension())->get();

        $this->assertArrayHasKey('id', $result);
        $this->assertIsString($result['id']);
        $this->assertNotEmpty($result['id']);
    }

    public function test_generates_uuid_when_no_x_request_id_header(): void
    {
        $id = (new RequestIdExtension())->get()['id'];

        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function test_uses_x_request_id_header_when_present(): void
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'custom-id-abc']);
        $this->app->instance('request', $request);

        $id = (new RequestIdExtension())->get()['id'];

        $this->assertSame('custom-id-abc', $id);
    }

    public function test_id_is_consistent_across_repeated_calls(): void
    {
        $ext = new RequestIdExtension();
        $id1 = $ext->get()['id'];
        $id2 = $ext->get()['id'];

        $this->assertSame($id1, $id2);
    }

    public function test_context_parameter_is_ignored(): void
    {
        $ext    = new RequestIdExtension();
        $result = $ext->get(['execution_ms' => 99.9]);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('execution_ms', $result);
    }
}
