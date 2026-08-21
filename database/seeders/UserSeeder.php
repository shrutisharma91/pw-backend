<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
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

        if (Role::where('name', 'superadmin')->exists()) {
            $admin->syncRoles(['superadmin']);
        }
    }
}
