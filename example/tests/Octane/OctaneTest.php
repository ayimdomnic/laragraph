<?php

declare(strict_types=1);

namespace Tests\Octane;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Http\Request;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Testing\Fakes\FakeClient;
use Laravel\Octane\Testing\Fakes\FakeWorker;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\TestCase;

/**
 * Runs requests through a real Octane worker: one application booted once,
 * every request handled in its own clone of it — exactly what Swoole,
 * RoadRunner and FrankenPHP workers do.
 */
class OctaneTest extends TestCase
{
    private string $database;

    /** @var array<string, string|false> */
    private array $environment = [];

    private FakeWorker $worker;

    private FakeClient $client;

    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Requests run in clones of one application, so they must share a real
        // database file — and a real cache: Octane empties the array store
        // after every request, which would drop subscriber registrations.
        $this->database = tempnam(sys_get_temp_dir(), 'laragraph-octane-').'.sqlite';
        touch($this->database);

        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'CACHE_STORE' => 'database'] as $key => $value) {
            $this->environment[$key] = getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        $this->client = new FakeClient([]);
        $this->worker = new FakeWorker(new ApplicationFactory(realpath(__DIR__.'/../..')), $this->client);
        $this->worker->boot();

        $this->app = (fn (): Application => $this->app)->call($this->worker);
        $this->app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }

        @unlink($this->database);

        // The worker's application installed Laravel's error and exception handlers.
        HandleExceptions::flushState($this);

        parent::tearDown();
    }

    /**
     * Send the operations through the worker, in order, each as its own request.
     *
     * @param  list<array{0: string, 1?: User|null}>  $operations  [query, user]
     * @return list<array<string, mixed>>
     */
    private function handle(array $operations): array
    {
        $this->client->requests = array_map(fn (array $operation): Request => Request::create('/graphql', 'POST', server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => isset($operation[1]) ? 'Bearer '.JWTAuth::fromUser($operation[1]) : null,
        ]), content: json_encode(['query' => $operation[0]], JSON_THROW_ON_ERROR)), $operations);
        $this->client->responses = [];

        $this->worker->run();

        $this->assertSame([], $this->client->errors);

        return array_map(fn ($response): array => json_decode($response->getContent(), true), $this->client->responses);
    }

    public function test_the_schema_is_compiled_once_per_worker(): void
    {
        $built = 0;
        $this->app['events']->listen(SchemaBuilt::class, function () use (&$built): void {
            $built++;
        });

        $this->handle([['{ me { id } }'], ['{ me { id } }'], ['{ organizations { edges { node { id } } } }']]);

        $this->assertSame(1, $built);
    }

    public function test_repeated_documents_are_validated_once_per_worker(): void
    {
        $this->handle([['{ me { id } }'], ['{ me { id } }'], ['{ me { id } }']]);

        $validated = (fn (): array => $this->validated)->call($this->app->make('laragraph'));
        $this->assertCount(1, $validated);
    }

    public function test_each_request_sees_only_its_own_user(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->in($organization)->create(['email' => 'member@octane.test']);
        $admin = User::factory()->admin()->in($organization)->create(['email' => 'admin@octane.test']);

        $results = $this->handle([
            ['{ me { email } }', $member],
            ['{ me { email } }'],
            ['{ me { email } }', $admin],
            ['{ me { email } }'],
        ]);

        $this->assertSame(
            ['member@octane.test', null, 'admin@octane.test', null],
            array_map(fn (array $result): ?string => $result['data']['me']['email'] ?? null, $results),
        );
    }

    public function test_subscription_updates_run_as_the_subscriber_not_the_publisher(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->in($organization)->create();
        $admin = User::factory()->admin()->in($organization)->create(['email' => 'admin@octane.test']);
        $this->assertSame(UserRole::Admin, $admin->role);

        $messages = [];
        $this->app['events']->listen(SubscriptionMessage::class, function (SubscriptionMessage $message) use (&$messages): void {
            $messages[] = $message->payload;
        });

        [$subscribed, $published, $after] = $this->handle([
            // The member may not see the admin's e-mail address…
            ["subscription { postPublished(organizationId: {$organization->id}) { title author { email } } }", $member],
            // …the admin publishing the post may.
            ['mutation { createPost(input: { title: "Octane", body: "Published from a worker.", publish: true }) { id } }', $admin],
            ['{ me { id } }'],
        ]);

        $this->assertNotNull($subscribed['extensions']['subscription']['subscriberId'] ?? null);
        $this->assertArrayNotHasKey('errors', $published);

        $this->assertCount(1, $messages);
        $this->assertSame(['title' => 'Octane', 'author' => ['email' => null]], $messages[0]['data']['postPublished']);

        // The next request is a guest again: nothing leaked from the broadcast.
        $this->assertNull($after['data']['me']);
    }
}
