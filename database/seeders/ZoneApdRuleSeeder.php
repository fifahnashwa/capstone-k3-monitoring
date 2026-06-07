<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Models\ZoneApdRule;
use Illuminate\Database\Seeder;

class ZoneApdRuleSeeder extends Seeder
{
    public function run(): void
    {
        $forklift = Zone::where('name', 'Forklift')->first();
        $emptyBox = Zone::where('name', 'Empty Box')->first();

        ZoneApdRule::insert([
            // Zona Forklift: semua 3 APD wajib
            ['zone_id' => $forklift->id, 'apd_label' => 'no_helmet', 'created_at' => now()],
            ['zone_id' => $forklift->id, 'apd_label' => 'no_vest',   'created_at' => now()],
            ['zone_id' => $forklift->id, 'apd_label' => 'no_boots',  'created_at' => now()],
            
            // Zona Empty Box: helm tidak wajib, tapi rompi dan sepatu tetap wajib  
            ['zone_id' => $emptyBox->id, 'apd_label' => 'no_vest',  'created_at' => now()],
            ['zone_id' => $emptyBox->id, 'apd_label' => 'no_boots', 'created_at' => now()],
        ]);
    }
}