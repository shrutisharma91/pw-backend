<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create test admin user
        User::firstOrCreate(
            ['email' => 'finzwork10@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('New@password123'),
                'email_verified_at' => now(),
                'role' => 'superadmin',
                'is_active' => true,
            ]
        );

        // Create another test user
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
