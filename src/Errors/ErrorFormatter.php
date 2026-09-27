<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Errors;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Error\Error;
use Illuminate\Support\Arr;

/**
 * The default error formatting/handling logic behind
 * {@see Laragraph::formatError()} and
 * {@see Laragraph::handleErrors()}.
 *
 * Kept as a plain static class (not an interface — this is formatting logic,
 * not a swappable collaborator) so it is unit-testable without going through
 * `Laragraph` itself. The two `Laragraph` methods stay in place under those
 * exact names, since `config('laragraph.error_formatter')`/`errors_handler`
 * are published into every consumer app as `[Laragraph::class, 'formatError']`
 * / `[Laragraph::class, 'handleErrors']` literal array-callables.
 */
final class ErrorFormatter
{
    /**
     * @return array<string, mixed>
     */
    public static function format(Error $error): array
    {
        // Only client-safe errors keep their message: anything else (a failed
        // query, a missing file…) could leak SQL, paths or secrets. In debug
        // mode the real message is still available as extensions.debugMessage.
        $message = $error->isClientSafe() ? $error->getMessage() : trans('laragraph::errors.internal.default');

        $formatted = [
            'message'   => $message ?: trans('laragraph::errors.internal.unexpected'),
            'locations' => $error->getLocations()
                ? array_map(
                    fn($loc): array => ['line' => $loc->line, 'column' => $loc->column],
                    $error->getLocations(),
                )
                : null,
            'path' => $error->getPath(),
            // Any previous exception implementing GraphQL\Error\ProvidesExtensions
            // (ValidationException, AuthorizationException, GraphQLException, or a
            // developer's own) is already surfaced here — Error's own constructor
            // pulls getExtensions() off $previous, so no instanceof chain is needed.
            'extensions' => $error->getExtensions() ?? [],
        ];

        $formatted['extensions']['category'] ??= $error->isClientSafe() ? 'graphql' : 'internal';

        $previous = $error->getPrevious();

        if (config('app.debug') && $previous instanceof \Throwable) {
            $formatted['extensions']['debugMessage'] = $previous->getMessage();
            $formatted['extensions']['trace'] = array_map(
                fn(array $frame) => Arr::only($frame, ['file', 'line', 'function', 'class']),
                array_slice($previous->getTrace(), 0, 10),
            );
        }

        return array_filter($formatted, fn(string|array|null $v): bool => $v !== null);
    }

    /**
     * @param array<int, Error> $errors
     * @param callable(Error):array<string, mixed> $formatter
     * @return array<int, array<string, mixed>>
     */
    public static function handle(array $errors, callable $formatter): array
    {
        return array_map($formatter, $errors);
    }
}
