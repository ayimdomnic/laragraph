<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Type as LaragraphType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class IsolationSecretType extends LaragraphType
{
    protected array $attributes = ['name' => 'IsolationSecret'];

    public function fields(): array
    {
        return ['ssn' => Type::string()];
    }
}

class IsolationPingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

class IsolationSecretQuery extends Query
{
    public function type(): Type
    {
        return app('laragraph')->type('IsolationSecret');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['ssn' => '000-00-0000'];
    }
}

class SchemaIsolationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', ['query' => ['ping' => IsolationPingQuery::class]]);
        $app['config']->set('laragraph.schemas.admin', [
            'query' => ['secret' => IsolationSecretQuery::class],
            'types' => ['IsolationSecret' => IsolationSecretType::class],
        ]);
    }

    public function test_schema_specific_types_do_not_leak_into_other_schemas(): void
    {
        // Build the admin schema first in the same process (as Octane, queue
        // workers and artisan commands do).
        $this->postJson('/graphql/admin', ['query' => '{ secret { ssn } }'])
            ->assertJsonPath('data.secret.ssn', '000-00-0000');

        $this->postJson('/graphql', ['query' => '{ __type(name: "IsolationSecret") { name } }'])
            ->assertJsonPath('data.__type', null);

        $manager = app(Laragraph::class);
        $this->assertArrayNotHasKey('IsolationSecret', $manager->schema('default')->getTypeMap());
        $this->assertArrayHasKey('IsolationSecret', $manager->schema('admin')->getTypeMap());
    }

    public function test_every_configured_schema_is_valid_when_built_in_one_process(): void
    {
        $manager = app(Laragraph::class);

        $manager->schema('admin')->assertValid();
        $manager->schema('default')->assertValid();

        $this->addToAssertionCount(2);
    }
}
