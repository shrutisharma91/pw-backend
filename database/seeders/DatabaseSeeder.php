<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed only login + RBAC. Demo merchants/loans/users are not auto-loaded.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            UserSeeder::class,
        ]);
    }
}
