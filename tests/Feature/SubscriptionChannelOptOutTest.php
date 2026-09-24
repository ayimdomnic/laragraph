<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Broadcasting\BroadcastManager;

class SubscriptionChannelOptOutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('laragraph.subscriptions.authorize_channel', false);
    }

    public function test_the_channel_rule_can_be_left_to_the_application(): void
    {
        $this->assertArrayNotHasKey(
            'graphql-subscriber.{subscriberId}',
            app(BroadcastManager::class)->driver()->getChannels()->all(),
        );
    }
}
