<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Local demo accounts only. The passwords are documented in README and must never be used in production.
     */
    public function run(): void
    {
        $users = [
            ['email' => 'admin@example.com', 'name' => 'Demo Admin', 'role' => UserRole::Admin],
            ['email' => 'staff@example.com', 'name' => 'Demo Staff', 'role' => UserRole::Staff],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'password' => 'password', 'role' => $user['role'], 'email_verified_at' => now()],
            );
        }
    }
}
