<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class CameraSeeder extends Seeder
{
    public function run(): void
    {
        $forklift = Zone::where('name', 'Forklift')->first();
        $emptyBox = Zone::where('name', 'Empty Box')->first();

        Camera::insert([
            [
                'zone_id'     => $forklift->id,
                'name'        => 'Kamera Forklift 1',
                'dvr_channel' => 'CH-01',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'zone_id'     => $emptyBox->id,
                'name'        => 'Kamera Empty Box 1',
                'dvr_channel' => 'CH-02',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }
}