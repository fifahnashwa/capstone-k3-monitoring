<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::insert([
            [
                'name'       => 'Admin K3',
                'email'      => 'admin@k3.com',
                'password'   => Hash::make('password123'),
                'role'       => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Manager Produksi',
                'email'      => 'manager@k3.com',
                'password'   => Hash::make('password123'),
                'role'       => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'HR Officer',
                'email'      => 'hr@k3.com',
                'password'   => Hash::make('password123'),
                'role'       => 'hr',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

