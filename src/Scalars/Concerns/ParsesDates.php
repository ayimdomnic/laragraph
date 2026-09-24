<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Concerns;

/**
 * Strict date parsing: formats are anchored with `!` so fields the input
 * does not contain (like the time of day) are zero instead of "now", and
 * out-of-range values such as 2024-02-31 are rejected instead of silently
 * rolling over into the next month.
 */
trait ParsesDates
{
    /**
     * @param list<string> $formats Tried in order; each is anchored with `!`.
     */
    private static function parseStrict(string $value, array $formats): ?\DateTimeImmutable
    {
        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed;
            }
        }

        return null;
    }
}
