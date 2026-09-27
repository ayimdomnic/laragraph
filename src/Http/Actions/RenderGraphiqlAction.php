<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Illuminate\Http\Response;

/**
 * Serve the GraphiQL browser IDE.
 */
final class RenderGraphiqlAction
{
    public function handle(string $schemaName = 'default'): Response
    {
        $endpoint = url(config('laragraph.route.prefix', 'graphql') . '/' . ($schemaName !== 'default' ? $schemaName : ''));

        return response()
            ->view('laragraph::graphiql', [
                'endpoint' => rtrim($endpoint, '/'),
                'title'    => config('laragraph.graphiql.title', 'Laragraph — GraphiQL'),
            ]);
    }
}
