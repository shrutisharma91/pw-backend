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
        $admin = User::updateOrCreate(
            ['email' => 'finzwork10@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('New@password123'),
                'email_verified_at' => now(),
                'role' => 'superadmin',
                'is_active' => true,
            ]
        );

        if (\Spatie\Permission\Models\Role::where('name', 'superadmin')->exists()) {
            $admin->syncRoles(['superadmin']);
        }

        $salesExec = User::updateOrCreate(
            ['email' => 'sales.exec@example.com'],
            [
                'name' => 'Sales Executive',
                'password' => Hash::make('New@password123'),
                'email_verified_at' => now(),
                'role' => 'sales_exec',
                'is_active' => true,
            ]
        );

        if (\Spatie\Permission\Models\Role::where('name', 'sales_exec')->exists()) {
            $salesExec->syncRoles(['sales_exec']);
        }

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
