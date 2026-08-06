<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Subscriptions;

use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRegistrar;
use Ayimdomnic\Laragraph\Tests\TestCase;

class SubscriptionRegistrarTest extends TestCase
{
    public function test_channel_is_null_before_capture(): void
    {
        $registrar = new SubscriptionRegistrar();

        $this->assertNull($registrar->channel());
    }

    public function test_capture_stores_the_channel(): void
    {
        $registrar = new SubscriptionRegistrar();

        $registrar->capture('users');

        $this->assertSame('users', $registrar->channel());
    }

    public function test_capture_accepts_a_list_of_channels(): void
    {
        $registrar = new SubscriptionRegistrar();

        $registrar->capture(['users', 'admins']);

        $this->assertSame(['users', 'admins'], $registrar->channel());
    }

    public function test_capture_overwrites_a_previous_value(): void
    {
        $registrar = new SubscriptionRegistrar();

        $registrar->capture('users');
        $registrar->capture('posts');

        $this->assertSame('posts', $registrar->channel());
    }
}
