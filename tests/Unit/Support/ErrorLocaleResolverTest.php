<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Support;

use Ayimdomnic\Laragraph\Support\ErrorLocaleResolver;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;

class ErrorLocaleResolverTest extends TestCase
{
    private function requestWithAcceptLanguage(?string $header): Request
    {
        $request = Request::create('/graphql', 'POST');

        if ($header !== null) {
            $request->headers->set('Accept-Language', $header);
        }

        return $request;
    }

    public function test_resolves_null_when_negotiation_is_disabled(): void
    {
        config(['laragraph.errors.negotiate_locale' => false]);

        $this->assertNull(ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage('fr')));
    }

    public function test_resolves_null_without_a_request(): void
    {
        config(['laragraph.errors.negotiate_locale' => true]);

        $this->assertNull(ErrorLocaleResolver::resolve(['not' => 'a request']));
    }

    public function test_negotiates_a_supported_locale(): void
    {
        config([
            'laragraph.errors.negotiate_locale'  => true,
            'laragraph.errors.supported_locales' => ['en', 'fr'],
        ]);

        $this->assertSame('fr', ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage('fr-FR,fr;q=0.9,en;q=0.8')));
    }

    public function test_falls_back_to_the_primary_subtag(): void
    {
        config([
            'laragraph.errors.negotiate_locale'  => true,
            'laragraph.errors.supported_locales' => ['en'],
        ]);

        $this->assertSame('en', ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage('en-US')));
    }

    public function test_returns_null_for_an_unsupported_language(): void
    {
        config([
            'laragraph.errors.negotiate_locale'  => true,
            'laragraph.errors.supported_locales' => ['en'],
        ]);

        $this->assertNull(ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage('de')));
    }

    public function test_rejects_a_malformed_locale_even_from_a_custom_resolver(): void
    {
        config([
            'laragraph.errors.locale_resolver' => fn(): string => '../../etc/passwd',
        ]);

        $this->assertNull(ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage(null)));
    }

    public function test_custom_resolver_takes_priority_and_is_checked_against_the_allow_list(): void
    {
        config([
            'laragraph.errors.locale_resolver'   => fn(): string => 'fr',
            'laragraph.errors.supported_locales' => ['en', 'fr'],
        ]);

        $this->assertSame('fr', ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage(null)));
    }

    public function test_custom_resolver_result_outside_the_allow_list_is_rejected(): void
    {
        config([
            'laragraph.errors.locale_resolver'   => fn(): string => 'de',
            'laragraph.errors.supported_locales' => ['en', 'fr'],
        ]);

        $this->assertNull(ErrorLocaleResolver::resolve($this->requestWithAcceptLanguage(null)));
    }
}
