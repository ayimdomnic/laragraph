<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Http;

use Ayimdomnic\Laragraph\Http\ResponseNegotiator;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;

class ResponseNegotiatorTest extends TestCase
{
    private function negotiator(): ResponseNegotiator
    {
        return new ResponseNegotiator();
    }

    public function test_plain_json_request_always_gets_200_by_default(): void
    {
        $request  = Request::create('/graphql', 'POST');
        $response = $this->negotiator()->negotiate($request, ['errors' => [['message' => 'bad']]]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function test_graphql_response_media_type_gets_400_when_there_is_no_data(): void
    {
        $request = Request::create('/graphql', 'POST', server: [
            'HTTP_ACCEPT' => 'application/graphql-response+json',
        ]);

        $response = $this->negotiator()->negotiate($request, ['errors' => [['message' => 'bad']]]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('application/graphql-response+json', (string) $response->headers->get('Content-Type'));
    }

    public function test_graphql_response_media_type_gets_200_when_there_is_data(): void
    {
        $request = Request::create('/graphql', 'POST', server: [
            'HTTP_ACCEPT' => 'application/graphql-response+json',
        ]);

        $response = $this->negotiator()->negotiate($request, ['data' => ['hello' => 'world']]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_an_explicit_status_overrides_negotiation(): void
    {
        $request  = Request::create('/graphql', 'POST');
        $response = $this->negotiator()->negotiate($request, ['data' => []], 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_a_batched_list_result_is_treated_as_successful(): void
    {
        $request = Request::create('/graphql', 'POST', server: [
            'HTTP_ACCEPT' => 'application/graphql-response+json',
        ]);

        $response = $this->negotiator()->negotiate($request, [['data' => []], ['data' => []]]);

        $this->assertSame(200, $response->getStatusCode());
    }
}
