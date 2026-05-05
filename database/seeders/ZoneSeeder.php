<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        Zone::insert([
            [
                'name'        => 'Forklift',
                'description' => 'Area operasional forklift. Wajib helm, rompi, dan boots.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'name'        => 'Empty Box',
                'description' => 'Area penyimpanan empty box. Wajib rompi dan boots, helm tidak wajib.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }
}