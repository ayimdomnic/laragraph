<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        Organization::firstOrCreate(
            ['name' => 'Acme Corporation'],
            [
                'slug' => 'acme-corporation',
                'email' => 'hello@acme.example',
                'phone' => '+1 555 0100',
                'address' => '1 Acme Plaza',
                'city' => 'Metropolis',
                'state' => 'NY',
                'country' => 'USA',
                'zip_code' => '10001',
                'description' => 'A sample organization for example seeding.',
                'status' => 'active',
                'settings' => ['timezone' => 'America/New_York'],
                'type' => 'company',
                'owner_id' => $user->id,
            ]
        );

        Organization::factory()->count(3)->create([
            'owner_id' => $user->id,
        ]);
    }
}
