<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Http\Request;

class RouteSchemaQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'secret';
    }
}

class RequireAdminHeader
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if ($request->header('X-Admin') !== 'yes') {
            return response()->json(['blocked' => true], 403);
        }

        return $next($request);
    }
}

class SchemaRouteMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['router']->aliasMiddleware('admin-only', RequireAdminHeader::class);

        $app['config']->set('laragraph.schemas.default', ['query' => ['value' => RouteSchemaQuery::class]]);
        $app['config']->set('laragraph.schemas.admin', [
            'query'      => ['value' => RouteSchemaQuery::class],
            'middleware' => ['admin-only'],
            'method'     => ['POST'],
        ]);
        $app['config']->set('laragraph.schemas.public', ['query' => ['value' => RouteSchemaQuery::class]]);
    }

    public function test_a_schemas_middleware_guards_its_endpoint(): void
    {
        $this->postJson('/graphql/admin', ['query' => '{ value }'])->assertForbidden()->assertJsonPath('blocked', true);

        $this->postJson('/graphql/admin', ['query' => '{ value }'], ['X-Admin' => 'yes'])
            ->assertOk()
            ->assertJsonPath('data.value', 'secret');
    }

    public function test_methods_a_schema_does_not_allow_are_rejected_not_rerouted(): void
    {
        // Previously this reached the catch-all route and ran the admin schema with no middleware.
        $this->get('/graphql/admin?query=' . urlencode('{ value }'))->assertStatus(405);
    }

    public function test_schemas_without_middleware_and_unknown_schemas_still_work(): void
    {
        $this->postJson('/graphql/public', ['query' => '{ value }'])->assertJsonPath('data.value', 'secret');
        $this->postJson('/graphql', ['query' => '{ value }'])->assertJsonPath('data.value', 'secret');
        $this->postJson('/graphql/unknown', ['query' => '{ value }'])->assertNotFound();
        $this->postJson('/graphql/adminx', ['query' => '{ value }'])->assertNotFound();
    }
}
