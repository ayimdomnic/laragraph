<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Exceptions;

/**
 * Thrown when a config setting enables a feature whose package isn't
 * installed — e.g. `laragraph.tracing.driver => 'otel'` without
 * `open-telemetry/api`, which is a suggested, not required, dependency.
 */
class MissingOptionalDependencyException extends \RuntimeException
{
    public static function forPackage(string $package, string $feature): self
    {
        return new self(
            "{$feature} requires the [{$package}] package, which is not installed. Run: composer require {$package}",
        );
    }
}
