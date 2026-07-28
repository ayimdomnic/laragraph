<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Exceptions;

use Ayimdomnic\Laragraph\Exceptions\AuthorizationException;
use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Exceptions\ValidationException;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class ExceptionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // AuthorizationException
    // -------------------------------------------------------------------------

    public function test_authorization_exception_is_client_safe(): void
    {
        $e = new AuthorizationException();
        $this->assertTrue($e->isClientSafe());
    }

    public function test_authorization_exception_default_message(): void
    {
        $e = new AuthorizationException();
        $this->assertSame('Unauthorized.', $e->getMessage());
    }

    public function test_authorization_exception_custom_message(): void
    {
        $e = new AuthorizationException('You shall not pass.');
        $this->assertSame('You shall not pass.', $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // ValidationException
    // -------------------------------------------------------------------------

    public function test_validation_exception_is_client_safe(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $e         = new ValidationException($validator);
        $this->assertTrue($e->isClientSafe());
    }

    public function test_validation_exception_message(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $e         = new ValidationException($validator);
        $this->assertSame('Validation failed.', $e->getMessage());
    }

    public function test_validation_exception_get_validation_errors(): void
    {
        $validator = Validator::make([], ['name' => 'required', 'email' => 'required|email']);
        $e         = new ValidationException($validator);

        $errors = $e->getValidationErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_validation_exception_get_validator(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $e         = new ValidationException($validator);
        $this->assertSame($validator, $e->getValidator());
    }

    // -------------------------------------------------------------------------
    // BatchingDisabledException
    // -------------------------------------------------------------------------

    public function test_batching_disabled_exception_message(): void
    {
        $e = new BatchingDisabledException();
        $this->assertSame('GraphQL batch requests are disabled.', $e->getMessage());
    }

    public function test_batching_disabled_exception_is_runtime_exception(): void
    {
        $e = new BatchingDisabledException();
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    // -------------------------------------------------------------------------
    // BatchLimitExceededException
    // -------------------------------------------------------------------------

    public function test_batch_limit_exceeded_exception_message_includes_limit(): void
    {
        $e = new BatchLimitExceededException(10);
        $this->assertSame('Batch size exceeds the maximum of 10 operations.', $e->getMessage());
    }

    public function test_batch_limit_exceeded_exception_custom_limit(): void
    {
        $e = new BatchLimitExceededException(5);
        $this->assertStringContainsString('5', $e->getMessage());
    }

    public function test_batch_limit_exceeded_exception_is_runtime_exception(): void
    {
        $e = new BatchLimitExceededException(10);
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }
}
