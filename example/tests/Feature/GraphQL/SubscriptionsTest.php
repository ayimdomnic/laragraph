<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberChannel;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Illuminate\Support\Facades\Event;

class SubscriptionsTest extends GraphQLTestCase
{
    private function subscribe(User $user, int|string $organizationId): string
    {
        return $this->graphql(
            'subscription ($org: ID!) { postPublished(organizationId: $org) { title author { name } } }',
            ['org' => $organizationId],
            $user,
        )
            ->assertJsonPath('extensions.subscription.channel', "organization.{$organizationId}.posts")
            ->json('extensions.subscription.subscriberId');
    }

    public function test_publishing_a_post_pushes_it_to_subscribers(): void
    {
        Event::fake([SubscriptionMessage::class]);

        $subscriberId = $this->subscribe($this->member, $this->acme->id);

        $post = Post::factory()->by($this->admin)->create(['title' => 'Quarterly update']);
        $this->graphql('mutation ($id: ID!) { publishPost(id: $id) { id } }', ['id' => $post->id], $this->admin);

        Event::assertDispatched(SubscriptionMessage::class, function (SubscriptionMessage $message) use ($subscriberId): bool {
            return $message->subscriberId === $subscriberId
                && $message->broadcastOn()->name === "private-graphql-subscriber.{$subscriberId}"
                && $message->payload['data']['postPublished'] === ['title' => 'Quarterly update', 'author' => ['name' => 'Ada Admin']];
        });
    }

    public function test_only_organization_members_can_subscribe(): void
    {
        $other = Organization::factory()->create();

        $this->graphql('subscription ($org: ID!) { postPublished(organizationId: $org) { id } }', ['org' => $other->id], $this->member)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->graphql('subscription ($org: ID!) { postPublished(organizationId: $org) { id } }', ['org' => $this->acme->id])
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }

    public function test_only_the_subscriber_may_join_its_private_channel(): void
    {
        $subscriberId = $this->subscribe($this->member, $this->acme->id);
        $channel = app(SubscriberChannel::class);

        $this->assertTrue($channel->join($this->member, $subscriberId));
        $this->assertFalse($channel->join($this->admin, $subscriberId));
    }

    public function test_subscribers_can_unsubscribe(): void
    {
        Event::fake([SubscriptionMessage::class]);
        $subscriberId = $this->subscribe($this->member, $this->acme->id);

        $this->deleteJson("/graphql/subscriptions/{$subscriberId}", [], $this->headersFor($this->admin))->assertNotFound();
        $this->deleteJson("/graphql/subscriptions/{$subscriberId}", [], $this->headersFor($this->member))->assertNoContent();

        $post = Post::factory()->by($this->admin)->create();
        $this->graphql('mutation ($id: ID!) { publishPost(id: $id) { id } }', ['id' => $post->id], $this->admin);

        Event::assertNotDispatched(SubscriptionMessage::class);
    }
}
