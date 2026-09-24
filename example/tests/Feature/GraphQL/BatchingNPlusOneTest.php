<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * batchRelation(): nested relations cost one query per relation, however
 * many parent rows are returned.
 */
class BatchingNPlusOneTest extends GraphQLTestCase
{
    private function addOrganizations(int $count): void
    {
        Organization::factory()->count($count)->create()->each(function (Organization $organization): void {
            User::factory()->count(2)->in($organization)->create()
                ->each(fn (User $user) => Post::factory()->count(2)->published()->by($user)->create());
        });
    }

    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->graphql('{
            organizations(first: 50) {
                edges { node { name memberCount members { name } posts { title author { name } } } }
            }
        }')->assertJsonMissingPath('errors');

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_does_not_grow_with_the_number_of_rows(): void
    {
        // Measure the database work, not response-cache hits.
        config(['laragraph.cache.response.enabled' => false]);

        $this->addOrganizations(2);
        $few = $this->countQueries();

        $this->addOrganizations(10);
        $many = $this->countQueries();

        $this->assertSame($few, $many);
        // total count + page of organizations + members + member counts (custom loader) + posts + post authors
        $this->assertSame(6, $many);
    }

    public function test_the_custom_member_count_loader(): void
    {
        $this->graphql('{ organization(slug: "acme") { memberCount } }')->assertJsonPath('data.organization.memberCount', 2);
    }
}
