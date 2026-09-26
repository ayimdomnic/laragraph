# 17. Error handling & localization

Laragraph gives every exception a structured, machine-readable shape under `extensions` — a
`code`, a `category`, and (for validation) the field errors — and can translate the messages your
users see, per request, based on the `Accept-Language` header.

## `GraphQLException`

This is the recommended way to report a domain/business error from a resolver. It's always
client-safe, its message is resolved through Laravel's translator, and its code plus any extra
context are exposed under `extensions` automatically:

```php
use Ayimdomnic\Laragraph\Exceptions\GraphQLException;

throw new GraphQLException(
    key: 'errors.out_of_stock',           // lang/en/errors.php
    errorCode: 'OUT_OF_STOCK',
    replace: ['product' => $product->name],
    extra: ['product_id' => $product->id],
);
```

```json
{
  "errors": [{
    "message": "Widget is out of stock.",
    "extensions": { "code": "OUT_OF_STOCK", "category": "application", "product_id": 42 }
  }]
}
```

`extra` always reaches the client — unlike `debugMessage`/`trace`, which only appear while
`APP_DEBUG=true` — so never put secrets or internal details there.

If the translation key doesn't resolve (no translation line exists in any locale), the message
falls back to the key itself — exactly like Laravel's own `trans()`/`__()` do — so a missing
translation is loud during development rather than silently blank.

### Generating one

```bash
php artisan laragraph:make:exception InvalidCredentialsException
```

Creates `app/GraphQL/Exceptions/InvalidCredentialsException.php` extending `GraphQLException`, with
the translation key and error code derived from the class name (`InvalidCredentialsException` →
`errors.invalid_credentials` / `INVALID_CREDENTIALS`). The example app's
[`InvalidCredentialsException`](../example/app/GraphQL/Exceptions/InvalidCredentialsException.php)
is generated output, thrown from
[`LoginMutation`](../example/app/GraphQL/Mutations/LoginMutation.php):

```php
if (! is_string($token)) {
    throw new InvalidCredentialsException();
}
```

## Why this is "first class" instead of a formatter hack

graphql-php already defines two interfaces for exactly this: `GraphQL\Error\ClientAware`
(`isClientSafe(): bool` — should the message reach the client) and `GraphQL\Error\ProvidesExtensions`
(`getExtensions(): ?array` — structured data for `extensions`). When a resolver throws, graphql-php
wraps the exception in an `Error` whose constructor *already* reads both interfaces off it. Laragraph's
`formatError()` just reads `$error->getExtensions()` — no `instanceof` chain, no special-casing.

That means your own exceptions get first-class treatment too, without touching `GraphQLException`
at all — just implement both interfaces:

```php
final class OutOfStockException extends \RuntimeException implements ClientAware, ProvidesExtensions
{
    public function isClientSafe(): bool { return true; }
    public function getExtensions(): ?array { return ['code' => 'OUT_OF_STOCK', 'category' => 'application']; }
}
```

`ValidationException` and `AuthorizationException` (thrown internally by `rules()` and
`authorize()`/`authorizeWithContext()`/`policy()` failures) implement both too, which is why their
`extensions.category` and, for validation, `extensions.validation` have always worked — they now
also carry `extensions.code` (`VALIDATION_FAILED` / `UNAUTHORIZED`).

**Scope note:** Laragraph does not offer a schema-level Result/Union payload pattern (e.g. every
mutation returning a `Success | ValidationError | NotFoundError` union) — `extensions.code` already
gives clients a stable, typed way to branch on error kind without forcing every field in your
schema to return a payload wrapper and every client query to unwrap it.

## Localization

Off by default — enabling it costs one `config()` call per request when disabled, and a locale
switch only around query execution when enabled.

```php
// config/laragraph.php
'errors' => [
    'negotiate_locale'  => true,           // resolve the locale from Accept-Language
    'supported_locales' => ['en', 'fr'],   // allow-list negotiate_locale may switch to
    'locale_resolver'   => null,           // or: fn (?Request $request): ?string
],
```

With `negotiate_locale` on, Laragraph parses the request's `Accept-Language` header (e.g.
`fr-CA,fr;q=0.9,en;q=0.8`), picks the first entry present in `supported_locales` (`fr-CA` matches a
supported `fr`), and switches the app locale for the duration of query execution — covering both
`GraphQLException` messages (translated at construction time) and `formatError()`'s own strings
(`Internal server error`, `Validation failed.`, …). The previous locale is always restored
afterwards, even if execution throws.

For full control — a user's saved locale preference, say — set `locale_resolver` to a callable; it
takes priority over `negotiate_locale` and its return value is still checked against
`supported_locales`.

```php
'locale_resolver' => fn (?Request $request): ?string => $request?->user()?->locale,
```

### Translating messages

- **Your own messages** (`GraphQLException` keys like `errors.out_of_stock`): add
  `lang/{locale}/errors.php` in your app, the normal Laravel way.
- **Laragraph's own messages** (`Validation failed.`, `Unauthorized.`, `Internal server error`, …):
  publish and translate the package's lang files:

  ```bash
  php artisan vendor:publish --tag=laragraph-lang
  ```

  This copies `lang/en/errors.php` to `lang/vendor/laragraph/en/errors.php`. Add a sibling locale
  directory (e.g. `lang/vendor/laragraph/fr/errors.php`) with as many or as few keys as you want —
  Laravel falls back to English (or your `app.fallback_locale`) for any key you don't override. The
  example app's
  [`lang/vendor/laragraph/fr/errors.php`](../example/lang/vendor/laragraph/fr/errors.php)
  demonstrates a partial override.

### Security: the `supported_locales` allow-list is not optional

`Accept-Language` is client-controlled input, and a locale is eventually used to build a
translation-file path. An unvalidated locale is therefore a path-traversal surface — Laravel's own
translator doesn't sanitize this either. Whenever `negotiate_locale` (or a custom `locale_resolver`)
is on, every candidate locale is:

1. checked against a strict tag format (`en`, `en-US`, `pt-BR`, …) with `preg_match`, and
2. checked against `supported_locales`, when that list is non-empty.

Either check failing means the request falls back to the application's current locale — never an
error, and never an unvalidated string reaching `App::setLocale()`. Set `supported_locales` to
exactly the locales you ship; leaving it as `[]` disables the allow-list check (format validation
still applies) and should only be done if you trust every locale your translator has files for.

### Known limitation

Locale negotiation wraps `Laragraph::execute()`, the path used by HTTP query/mutation requests
(including batched ones). Subscription broadcast replays
(`SubscriptionManager::executeQuery()`) don't go through `execute()` and are formatted using
whatever locale is ambient on the queue worker — not the original subscriber's negotiated locale.

## Testing

The example app's
[`LocalizationTest`](../example/tests/Feature/GraphQL/LocalizationTest.php) covers the whole path
end-to-end: a translated message via `Accept-Language`, the English default without the header, an
unsupported language falling back to English, locale restoration after the request, and a vendor
lang override. Run it with:

```bash
cd example && vendor/bin/phpunit --filter=LocalizationTest
```
