<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Exceptions;

use Ayimdomnic\Laragraph\Exceptions\AuthorizationException;
use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Exceptions\GraphQLException;
use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\Exceptions\ValidationException;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\Lang;
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

    public function test_authorization_exception_extensions(): void
    {
        $e = new AuthorizationException();
        $this->assertSame(['code' => 'UNAUTHORIZED', 'category' => 'authorization'], $e->getExtensions());
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

    public function test_validation_exception_extensions(): void
    {
        $validator = Validator::make([], ['name' => 'required']);
        $e         = new ValidationException($validator);

        $extensions = $e->getExtensions();
        $this->assertSame('VALIDATION_FAILED', $extensions['code']);
        $this->assertSame('validation', $extensions['category']);
        $this->assertArrayHasKey('name', $extensions['validation']);
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

    public function test_batching_disabled_exception_is_a_request_exception_with_a_code(): void
    {
        $e = new BatchingDisabledException();
        $this->assertInstanceOf(RequestException::class, $e);
        $this->assertSame('BATCHING_DISABLED', $e->errorCode);
        $this->assertSame(400, $e->status);
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

    public function test_batch_limit_exceeded_exception_is_a_request_exception_with_a_code(): void
    {
        $e = new BatchLimitExceededException(10);
        $this->assertInstanceOf(RequestException::class, $e);
        $this->assertSame('BATCH_LIMIT_EXCEEDED', $e->errorCode);
        $this->assertSame(400, $e->status);
    }

    // -------------------------------------------------------------------------
    // GraphQLException
    // -------------------------------------------------------------------------

    public function test_graphql_exception_is_client_safe(): void
    {
        $e = new GraphQLException('errors.missing_key', 'SOME_CODE');
        $this->assertTrue($e->isClientSafe());
    }

    public function test_graphql_exception_falls_back_to_the_key_when_untranslated(): void
    {
        $e = new GraphQLException('errors.this_key_has_no_translation', 'SOME_CODE');
        $this->assertSame('errors.this_key_has_no_translation', $e->getMessage());
    }

    public function test_graphql_exception_uses_the_translated_message(): void
    {
        Lang::addLines(['errors.greeting' => 'Hello, :name!'], 'en');

        $e = new GraphQLException('errors.greeting', 'GREETING', ['name' => 'Ada']);
        $this->assertSame('Hello, Ada!', $e->getMessage());
    }

    public function test_graphql_exception_explicit_message_overrides_translation(): void
    {
        $e = new GraphQLException('errors.missing_key', 'SOME_CODE', message: 'Explicit message.');
        $this->assertSame('Explicit message.', $e->getMessage());
    }

    public function test_graphql_exception_extensions_include_code_category_and_extra(): void
    {
        $e = new GraphQLException(
            'errors.missing_key',
            'SOME_CODE',
            extra: ['field' => 'email'],
            category: 'application',
        );

        $this->assertSame(
            ['code' => 'SOME_CODE', 'category' => 'application', 'field' => 'email'],
            $e->getExtensions(),
        );
    }
}
