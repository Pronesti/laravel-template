<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Deliberately NOT using WithoutModelEvents: Seeder::__invoke would then wrap run()
    // in Model::withoutEvents(), suppressing HasExternalId's `creating` hook and leaving
    // users.external_id null, which violates its NOT NULL constraint.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
