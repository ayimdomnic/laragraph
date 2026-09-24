<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a small, predictable data set for exploring the API:
     *
     *   admin@example.com / password   — admin of "Acme Corporation"
     *   member@example.com / password  — member of "Acme Corporation"
     */
    public function run(): void
    {
        $acme = Organization::firstOrCreate(['slug' => 'acme-corporation'], [
            'name' => 'Acme Corporation',
            'email' => 'hello@acme.example',
            'city' => 'Metropolis',
            'country' => 'USA',
            'description' => 'A sample organization.',
            'status' => 'active',
            'settings' => ['timezone' => 'America/New_York', 'features' => ['blog' => true]],
            'type' => 'company',
        ]);

        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Ada Admin',
            'password' => 'password',
            'role' => UserRole::Admin,
            'organization_id' => $acme->id,
        ]);

        $member = User::firstOrCreate(['email' => 'member@example.com'], [
            'name' => 'Mo Member',
            'password' => 'password',
            'role' => UserRole::Member,
            'organization_id' => $acme->id,
        ]);

        $acme->update(['owner_id' => $admin->id]);

        Post::factory()->count(3)->published()->by($admin)->create();
        Post::factory()->count(2)->published()->by($member)->create();
        Post::factory()->by($member)->create(['title' => 'An unpublished draft']);

        Organization::factory()->count(3)->create(['owner_id' => $admin->id]);
    }
}
