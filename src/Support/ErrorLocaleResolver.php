<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use Ayimdomnic\Laragraph\Laragraph;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * Resolves which locale error messages should be translated into for a
 * single request — used by {@see Laragraph::execute()}
 * to temporarily switch the application locale around query execution.
 *
 * Disabled by default: `resolve()` returns null (no-op, one `config()` call)
 * unless `laragraph.errors.locale_resolver` or `laragraph.errors.negotiate_locale`
 * is configured.
 */
final class ErrorLocaleResolver
{
    /** Locale tags must look like this before they are ever passed to `App::setLocale()`. */
    private const VALID_LOCALE = '/^[A-Za-z]{2,8}(?:[_-][A-Za-z0-9]{2,8})?$/';

    /**
     * @return string|null The locale to switch to for this request, or null to
     *                     leave the application's current locale untouched.
     */
    public static function resolve(mixed $context): ?string
    {
        $resolver = config('laragraph.errors.locale_resolver');
        $request  = self::extractRequest($context);

        if (is_callable($resolver)) {
            $locale = $resolver($request);

            return is_string($locale) ? self::sanitize($locale) : null;
        }

        if (!config('laragraph.errors.negotiate_locale', false) || !$request instanceof Request) {
            return null;
        }

        $header = $request->header('Accept-Language');

        if (!is_string($header) || $header === '') {
            return null;
        }

        return self::negotiate($header);
    }

    private static function negotiate(string $header): ?string
    {
        $supported = array_map(strtolower(...), (array) config('laragraph.errors.supported_locales', ['en']));

        foreach (AcceptHeader::fromString($header)->all() as $item) {
            $tag = strtolower($item->getValue());

            if (in_array($tag, $supported, true)) {
                return self::sanitize($tag);
            }

            // "fr-CA" negotiates down to the "fr" the app actually supports.
            $primary = strtolower((string) strtok($tag, '-_'));

            if (in_array($primary, $supported, true)) {
                return self::sanitize($primary);
            }
        }

        return null;
    }

    /**
     * An Accept-Language value (or a custom resolver's return) is untrusted
     * input: it eventually becomes part of a translation-file path, so it is
     * always checked against a strict tag format and, when configured, the
     * `supported_locales` allow-list — never passed through unvalidated.
     */
    private static function sanitize(string $locale): ?string
    {
        if (preg_match(self::VALID_LOCALE, $locale) !== 1) {
            return null;
        }

        $supported = (array) config('laragraph.errors.supported_locales', []);

        if ($supported !== [] && !in_array(strtolower($locale), array_map(strtolower(...), $supported), true)) {
            return null;
        }

        return $locale;
    }

    private static function extractRequest(mixed $context): ?Request
    {
        return $context instanceof Request ? $context : null;
    }
}
