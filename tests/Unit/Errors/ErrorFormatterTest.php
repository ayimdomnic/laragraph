<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Errors;

use Ayimdomnic\Laragraph\Errors\ErrorFormatter;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;

class ErrorFormatterTest extends TestCase
{
    public function test_internal_exception_messages_are_hidden_from_clients(): void
    {
        config(['app.debug' => false]);

        $error = ErrorFormatter::format(Error::createLocatedError(new \RuntimeException('SQLSTATE[42S02]: secret_table')));

        $this->assertSame('Internal server error', $error['message']);
        $this->assertSame('internal', $error['extensions']['category']);
        $this->assertArrayNotHasKey('debugMessage', $error['extensions']);
    }

    public function test_client_safe_error_messages_are_kept(): void
    {
        $error = ErrorFormatter::format(new Error('The provided credentials are incorrect.'));

        $this->assertSame('The provided credentials are incorrect.', $error['message']);
        $this->assertSame('graphql', $error['extensions']['category']);
    }

    public function test_debug_mode_includes_debug_message_and_trace(): void
    {
        config(['app.debug' => true]);

        $error = ErrorFormatter::format(Error::createLocatedError(new \RuntimeException('boom')));

        $this->assertSame('Internal server error', $error['message']);
        $this->assertSame('boom', $error['extensions']['debugMessage']);
        $this->assertArrayHasKey('trace', $error['extensions']);
    }

    public function test_handle_maps_the_formatter_over_every_error(): void
    {
        $errors    = [new Error('one'), new Error('two')];
        $formatted = ErrorFormatter::handle($errors, fn(Error $e): array => ['message' => $e->getMessage()]);

        $this->assertCount(2, $formatted);
        $this->assertSame('one', $formatted[0]['message']);
        $this->assertSame('two', $formatted[1]['message']);
    }
}
