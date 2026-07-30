<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::updateOrCreate(
            ['email' => 'livetest@crema.supply'],
            [
                'name' => 'Live Test User',
                'password' => Hash::make('password'),
                'whatsapp_number' => '+6281234567890',
                'is_active' => true,
            ]
        );

        Customer::updateOrCreate(
            ['email' => 'farah@tempa.dev'],
            [
                'name' => 'Bobi Hartanto',
                'password' => Hash::make('password'),
                'whatsapp_number' => '+6281234567891',
                'is_active' => true,
            ]
        );
    }
}
