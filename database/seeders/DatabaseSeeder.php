<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan penting: dependent seeders harus jalan setelah induknya.
        $this->call([
            UserSeeder::class,
            ZoneSeeder::class,
            ZoneApdRuleSeeder::class,
            CameraSeeder::class,
            ShiftSeeder::class,
            ViolationSeeder::class,   // demo data lintas semua state
        ]);
    }
}