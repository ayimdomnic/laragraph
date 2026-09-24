<?php

declare(strict_types=1);

namespace App\GraphQL\Extensions;

use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;

/**
 * CUSTOM RESPONSE EXTENSION — adds `extensions.apiVersion` to every
 * response. Registered in AppServiceProvider::boot().
 */
class ApiVersionExtension implements GraphQLExtensionInterface
{
    public function key(): string
    {
        return 'apiVersion';
    }

    public function get(array $context = []): array
    {
        return ['version' => config('app.api_version', '2026-09')];
    }
}
