<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\File;

class NestedDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('GraphQL'));

        parent::tearDown();
    }

    private function write(string $relative, string $namespace, string $body): void
    {
        $path = app_path("GraphQL/{$relative}");
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, "<?php\nnamespace {$namespace};\n{$body}\n");
        require_once $path;
    }

    public function test_classes_in_subdirectories_are_discovered_with_their_namespace(): void
    {
        $this->write('Queries/Billing/InvoicesQuery.php', 'App\\GraphQL\\Queries\\Billing', <<<'PHP'
class InvoicesQuery extends \Ayimdomnic\Laragraph\Support\Query {
    public function type(): \GraphQL\Type\Definition\Type { return \GraphQL\Type\Definition\Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed { return 'nested'; }
}
PHP);
        $this->write('Types/Billing/Deep/InvoiceStatusType.php', 'App\\GraphQL\\Types\\Billing\\Deep', <<<'PHP'
class InvoiceStatusType extends \Ayimdomnic\Laragraph\Support\EnumType {
    public function values(): array { return ['PAID' => ['value' => 'paid']]; }
}
PHP);
        File::put(app_path('GraphQL/Types/Billing/notes.txt'), 'not php');

        $this->assertSame(['invoices' => 'App\\GraphQL\\Queries\\Billing\\InvoicesQuery'], Discover::queries('app/GraphQL/Queries'));
        $this->assertSame(['InvoiceStatus' => 'App\\GraphQL\\Types\\Billing\\Deep\\InvoiceStatusType'], Discover::types('app/GraphQL/Types'));

        config(['laragraph.discover.queries' => 'app/GraphQL/Queries']);
        $this->app->forgetInstance('laragraph');

        $this->assertSame('nested', $this->graphql('{ invoices }')['data']['invoices'] ?? null);
    }
}
