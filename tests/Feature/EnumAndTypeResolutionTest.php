<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\EnumType;
use Ayimdomnic\Laragraph\Support\InputType;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\Description;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\File;

#[Description('Lifecycle state of an account.')]
enum AccountStatus: string
{
    case Active = 'active';

    #[Description('Suspended by a moderator.')]
    case Banned = 'banned';
}

enum Priority
{
    case Low;
    case High;
}

class AccountStatusWrapperEnum extends EnumType
{
    protected array $attributes = ['name' => 'AccountStatusWrapped'];

    public function values(): array
    {
        return AccountStatus::cases();
    }
}

class RenameAccountInput extends InputType
{
    protected array $attributes = ['name' => 'RenameAccountInput'];

    public function fields(): array
    {
        return ['name' => Type::nonNull(Type::string())];
    }
}

class AccountStatusQuery extends Query
{
    public static mixed $received = null;

    public function type(): Type
    {
        return app('laragraph')->type('AccountStatus');
    }

    public function args(): array
    {
        return ['is' => ['type' => app('laragraph')->type('AccountStatus')]];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        self::$received = $args['is'] ?? null;

        return $args['is'] ?? AccountStatus::Banned;
    }
}

class WrappedStatusQuery extends Query
{
    public function type(): Type
    {
        return app('laragraph')->type('AccountStatusWrapped');
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return AccountStatus::Active;
    }
}

class RenameAccountMutation extends Mutation
{
    public function type(): Type
    {
        return Type::string();
    }

    public function args(): array
    {
        // Aliased as `RenameInput` below, while its GraphQL name is `RenameAccountInput`.
        return ['input' => ['type' => Type::nonNull(app('laragraph')->type('RenameInput'))]];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'renamed to ' . $args['input']['name'];
    }
}

class EnumAndTypeResolutionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.types', [
            'AccountStatus'        => AccountStatus::class,
            'Priority'             => Priority::class,
            'AccountStatusWrapped' => AccountStatusWrapperEnum::class,
            'RenameInput'          => RenameAccountInput::class,
        ]);
        $app['config']->set('laragraph.schemas.default', [
            'query' => [
                'accountStatus' => AccountStatusQuery::class,
                'wrappedStatus' => WrappedStatusQuery::class,
            ],
            'mutation' => ['renameAccount' => RenameAccountMutation::class],
        ]);
    }

    private function builtManager(): Laragraph
    {
        $manager = app(Laragraph::class);
        $manager->schema();

        return $manager;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('GraphQL'));

        parent::tearDown();
    }

    public function test_native_enums_are_registered_as_graphql_enums(): void
    {
        $type = $this->builtManager()->type('AccountStatus');

        $this->assertInstanceOf(PhpEnumType::class, $type);
        $this->assertSame('AccountStatus', $type->name);
        $this->assertSame('Lifecycle state of an account.', $type->description);
        $this->assertSame('Suspended by a moderator.', $type->getValue('Banned')?->description);
    }

    public function test_native_enum_cases_serialize_and_parse(): void
    {
        $this->assertSame('Banned', $this->graphql('{ accountStatus }')['data']['accountStatus']);

        $result = $this->graphql('query ($is: AccountStatus) { accountStatus(is: $is) }', ['is' => 'Active']);

        $this->assertSame('Active', $result['data']['accountStatus']);
        $this->assertSame(AccountStatus::Active, AccountStatusQuery::$received);
    }

    public function test_pure_enums_are_supported(): void
    {
        $type = $this->builtManager()->type('Priority');

        $this->assertInstanceOf(PhpEnumType::class, $type);
        $this->assertSame(['Low', 'High'], array_map(fn(EnumValueDefinition $value): string => $value->name, $type->getValues()));
    }

    public function test_enum_type_values_accepts_native_enum_cases(): void
    {
        $this->assertSame('Active', $this->graphql('{ wrappedStatus }')['data']['wrappedStatus']);
    }

    public function test_enum_type_values_keeps_explicit_definitions(): void
    {
        $type = new class extends EnumType {
            protected array $attributes = ['name' => 'Explicit'];

            public function values(): array
            {
                return ['ON' => ['value' => 1], 'OFF' => ['value' => 0]];
            }
        };

        $this->assertSame(['ON', 'OFF'], array_map(fn(EnumValueDefinition $value): string => $value->name, $type->getValues()));
    }

    public function test_variables_resolve_types_whose_alias_differs_from_their_name(): void
    {
        $result = $this->graphql(
            'mutation ($input: RenameAccountInput!) { renameAccount(input: $input) }',
            ['input' => ['name' => 'Ada']],
        );

        $this->assertSame('renamed to Ada', $result['data']['renameAccount'] ?? $result);
    }

    public function test_type_by_name_returns_null_for_unknown_names(): void
    {
        $this->assertNull(app(Laragraph::class)->typeByName('DoesNotExist'));
    }

    public function test_enum_type_name_is_inferred_from_the_class(): void
    {
        $manager = app(Laragraph::class);
        $manager->addType(Priority::class);

        $this->assertTrue($manager->hasType('Priority'));
    }

    public function test_discovery_finds_every_named_type_kind_and_native_enums(): void
    {
        $dir = app_path('GraphQL/Types');
        File::ensureDirectoryExists($dir);

        $files = [
            'DiscoveredColor' => 'enum DiscoveredColor { case Red; case Blue; }',
            'DiscoveredFilterInputType' => <<<'PHP'
class DiscoveredFilterInputType extends \Ayimdomnic\Laragraph\Support\InputType {
    protected array $attributes = ['name' => 'DiscoveredFilterInput'];
    public function fields(): array { return ['term' => \GraphQL\Type\Definition\Type::string()]; }
}
PHP,
            'DiscoveredModeType' => <<<'PHP'
class DiscoveredModeType extends \Ayimdomnic\Laragraph\Support\EnumType {
    public function values(): array { return ['FAST' => ['value' => 'fast']]; }
}
PHP,
            'NotAGraphQLType' => 'class NotAGraphQLType {}',
        ];

        foreach ($files as $class => $body) {
            file_put_contents("{$dir}/{$class}.php", "<?php\nnamespace App\\GraphQL\\Types;\n{$body}\n");
            require_once "{$dir}/{$class}.php";
        }

        $discovered = Discover::types('app/GraphQL/Types');

        $this->assertSame('App\\GraphQL\\Types\\DiscoveredColor', $discovered['DiscoveredColor'] ?? null);
        $this->assertSame('App\\GraphQL\\Types\\DiscoveredFilterInputType', $discovered['DiscoveredFilterInput'] ?? null);
        $this->assertSame('App\\GraphQL\\Types\\DiscoveredModeType', $discovered['DiscoveredMode'] ?? null);
        $this->assertArrayNotHasKey('NotAGraphQLType', $discovered);
    }

    public function test_explicitly_registered_classes_are_not_registered_twice_by_discovery(): void
    {
        $dir = app_path('GraphQL/Types');
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/TwiceType.php", <<<'PHP'
<?php
namespace App\GraphQL\Types;
class TwiceType extends \Ayimdomnic\Laragraph\Support\Type {
    protected array $attributes = ['name' => 'Twice'];
    public function fields(): array { return ['id' => \GraphQL\Type\Definition\Type::id()]; }
}
PHP);
        require_once "{$dir}/TwiceType.php";
        file_put_contents("{$dir}/OnceType.php", <<<'PHP'
<?php
namespace App\GraphQL\Types;
class OnceType extends \Ayimdomnic\Laragraph\Support\Type {
    protected array $attributes = ['name' => 'Once'];
    public function fields(): array { return ['id' => \GraphQL\Type\Definition\Type::id()]; }
}
PHP);
        require_once "{$dir}/OnceType.php";

        config([
            'laragraph.discover.types' => 'app/GraphQL/Types',
            'laragraph.types'          => [...config('laragraph.types'), 'TwiceAlias' => 'App\\GraphQL\\Types\\TwiceType'],
        ]);

        $manager = app(Laragraph::class);
        $manager->schema()->assertValid();

        $this->assertArrayNotHasKey('Twice', $manager->getTypes());
        $this->assertArrayHasKey('TwiceAlias', $manager->getTypes());
        $this->assertArrayHasKey('Once', $manager->getTypes());
        $this->assertSame('Once', $manager->schema()->getType('Once')?->name);
    }
}
