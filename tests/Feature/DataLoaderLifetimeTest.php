<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\DataLoader\BatchResolver;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Overblog\DataLoader\DataLoader;

class EchoLoader extends BatchResolver
{
    public static int $batches = 0;

    public function batch(array $keys): array
    {
        self::$batches++;

        return array_map(fn(string $key): string => "loaded:{$key}", $keys);
    }
}

class LoaderBackedQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function args(): array
    {
        return ['key' => ['type' => Type::string(), 'defaultValue' => 'a']];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return DataLoaderRegistry::for($context)?->get(EchoLoader::class)->load($args['key']);
    }
}

class DataLoaderLifetimeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', ['value' => LoaderBackedQuery::class]);
    }

    /**
     * @return array<int, DataLoader>
     */
    private function liveLoaders(): array
    {
        return (new \ReflectionProperty(DataLoader::class, 'instances'))->getValue();
    }

    public function test_loaders_are_released_after_every_execution(): void
    {
        $before = count($this->liveLoaders());

        for ($i = 0; $i < 50; $i++) {
            $this->postJson('/graphql', ['query' => '{ value }'])->assertJsonPath('data.value', 'loaded:a');
        }

        // Previously every request left its loaders (and cached rows) behind for good.
        $this->assertCount($before, $this->liveLoaders());
    }

    public function test_batching_still_works_within_one_execution(): void
    {
        EchoLoader::$batches = 0;

        $this->postJson('/graphql', ['query' => '{ a: value(key: "x") b: value(key: "y") c: value(key: "x") }'])
            ->assertJsonPath('data', ['a' => 'loaded:x', 'b' => 'loaded:y', 'c' => 'loaded:x']);

        $this->assertSame(1, EchoLoader::$batches);
    }

    public function test_results_are_not_cached_across_executions(): void
    {
        EchoLoader::$batches = 0;

        $this->postJson('/graphql', ['query' => '{ value }']);
        $this->postJson('/graphql', ['query' => '{ value }']);

        $this->assertSame(2, EchoLoader::$batches);
    }
}
