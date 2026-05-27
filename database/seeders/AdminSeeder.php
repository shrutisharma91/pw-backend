<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123456'),
                'email_verified_at' => now(),
            ]
        );

        echo "✓ Super Admin created successfully!\n";
        echo "  Email: admin@example.com\n";
        echo "  Password: Admin@123456\n";
    }
}
