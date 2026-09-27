<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Exceptions;

use Ayimdomnic\Laragraph\Exceptions\MissingOptionalDependencyException;
use Ayimdomnic\Laragraph\Tests\TestCase;

class MissingOptionalDependencyExceptionTest extends TestCase
{
    public function test_the_message_names_the_package_the_feature_and_the_fix(): void
    {
        $exception = MissingOptionalDependencyException::forPackage(
            'open-telemetry/api',
            "The 'otel' tracing driver",
        );

        $this->assertStringContainsString('open-telemetry/api', $exception->getMessage());
        $this->assertStringContainsString("The 'otel' tracing driver", $exception->getMessage());
        $this->assertStringContainsString('composer require open-telemetry/api', $exception->getMessage());
    }

    public function test_it_is_a_runtime_exception(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, MissingOptionalDependencyException::forPackage('foo/bar', 'A feature'));
    }
}
