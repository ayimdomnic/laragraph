<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Http;

use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;

class GraphQLContextTest extends TestCase
{
    public function test_wraps_a_request_preserving_input_headers_and_user_resolver(): void
    {
        $request = Request::create('/graphql', 'POST', ['foo' => 'bar'], server: ['HTTP_X_TRACE' => 'abc']);
        $request->setUserResolver(fn(): string => 'the-user');

        $context = GraphQLContext::fromRequest($request);

        $this->assertNotSame($request, $context);
        $this->assertSame('bar', $context->input('foo'));
        $this->assertSame('abc', $context->header('X-Trace'));
        $this->assertSame('the-user', $context->user());
        $this->assertNull($context->dataLoaders);
        $this->assertFalse($context->subscribing);
        $this->assertNull($context->subscriptionRegistrar);
    }

    public function test_returns_an_existing_context_untouched(): void
    {
        $context = GraphQLContext::fromRequest(Request::create('/graphql'));

        $this->assertSame($context, GraphQLContext::fromRequest($context));
    }

    public function test_client_input_cannot_shadow_the_subscribing_flag(): void
    {
        $context = GraphQLContext::fromRequest(Request::create('/graphql', 'POST', ['subscribing' => true]));

        $this->assertFalse($context->subscribing);
    }
}
