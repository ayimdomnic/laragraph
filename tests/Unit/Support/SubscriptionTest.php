<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Support;

use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ConcreteSubscription extends Subscription
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return $root; }
}

class SubscriptionWithChannel extends Subscription
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return $root; }
    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'my-channel'; }
}

class SubscriptionTest extends TestCase
{
    public function test_to_array_includes_subscribe_key(): void
    {
        $field = (new ConcreteSubscription())->toArray();
        $this->assertArrayHasKey('subscribe', $field);
        $this->assertIsCallable($field['subscribe']);
    }

    public function test_subscribe_returns_channel_name(): void
    {
        $sub   = new SubscriptionWithChannel();
        $field = $sub->toArray();

        $result = ($field['subscribe'])(null, [], null, $this->createMock(ResolveInfo::class));
        $this->assertSame('my-channel', $result);
    }

    public function test_default_subscribe_returns_null(): void
    {
        $sub   = new ConcreteSubscription();
        $field = $sub->toArray();

        $result = ($field['subscribe'])(null, [], null, $this->createMock(ResolveInfo::class));
        $this->assertNull($result);
    }
}
