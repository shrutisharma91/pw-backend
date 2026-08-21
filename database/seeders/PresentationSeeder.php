<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Frozen dataset for Shruti's Super Admin phases only:
 * 1 Auth, 2 Command Center, 3 Users (base logins), 11–14 via existing seeders.
 *
 * Do not re-run migrate:fresh before the presentation — same seed = same numbers.
 */
class PresentationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            UserSeeder::class,
            DemoDataSeeder::class,
            Phase2CommandCenterSeeder::class,
            Phase11AnalyticsSeeder::class,
            Phase12NotificationsSeeder::class,
            Phase13Seeder::class,
            Phase14Seeder::class,
        ]);
    }
}
