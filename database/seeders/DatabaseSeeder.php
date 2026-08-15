<?php

namespace Database\Seeders;

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
        $this->call([
            RbacSeeder::class,
            UserSeeder::class,
            DemoDataSeeder::class,
            CustomerPortalSeeder::class,
            Phase3LenderOpsSeeder::class,
            Phase6SalesExecSeeder::class,
            Phase13Seeder::class,
            Phase14Seeder::class,
        ]);
    }
}
