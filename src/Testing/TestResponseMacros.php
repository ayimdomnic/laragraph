<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Testing;

use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registers `TestResponse` macros for the GraphQL error shape Laragraph
 * itself produces (`extensions.category`/`extensions.code`/`extensions.validation`
 * — see docs/03-queries-and-mutations.md#errors), matching the assertion
 * idioms every hand-written test in this package and its example app already
 * repeats. Registered once from {@see LaragraphServiceProvider::boot()}.
 */
final class TestResponseMacros
{
    public static function register(): void
    {
        TestResponse::macro('assertGraphQLErrors', function (): TestResponse {
            /** @var TestResponse<Response> $this */
            return $this->assertJsonPath('errors', fn(mixed $errors): bool => is_array($errors) && $errors !== []);
        });

        TestResponse::macro('assertNoGraphQLErrors', function (): TestResponse {
            /** @var TestResponse<Response> $this */
            return $this->assertJsonPath('errors', fn(mixed $errors): bool => $errors === null || $errors === []);
        });

        TestResponse::macro('assertGraphQLErrorCategory', function (string $category): TestResponse {
            /** @var TestResponse<Response> $this */
            return $this->assertJsonPath('errors.0.extensions.category', $category);
        });

        TestResponse::macro('assertGraphQLErrorCode', function (string $code): TestResponse {
            /** @var TestResponse<Response> $this */
            return $this->assertJsonPath('errors.0.extensions.code', $code);
        });

        TestResponse::macro('assertGraphQLValidationError', function (string $field, ?string $message = null): TestResponse {
            /** @var TestResponse<Response> $this */
            $this->assertJsonPath('errors.0.extensions.category', 'validation');
            $this->assertJsonPath(
                "errors.0.extensions.validation.{$field}",
                fn(mixed $messages): bool => is_array($messages) && $messages !== [],
            );

            if ($message !== null) {
                $this->assertJsonPath(
                    "errors.0.extensions.validation.{$field}",
                    fn(array $messages): bool => in_array($message, $messages, true),
                );
            }

            return $this;
        });

        TestResponse::macro('assertGraphQLData', function (string $path, mixed $value): TestResponse {
            /** @var TestResponse<Response> $this */
            return $this->assertJsonPath("data.{$path}", $value);
        });
    }
}
