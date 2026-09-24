<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'status' => PostStatus::Draft,
            'published_at' => null,
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => PostStatus::Published, 'published_at' => now()]);
    }

    public function by(User $author): static
    {
        return $this->state(fn (): array => ['user_id' => $author->id, 'organization_id' => $author->organization_id]);
    }
}
