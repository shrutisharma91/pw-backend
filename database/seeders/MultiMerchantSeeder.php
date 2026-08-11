<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MultiMerchantSeeder extends Seeder
{
    public function run(): void
    {
        $reliance = Merchant::firstOrCreate(
            ['gst_number' => '27AAAAA1234B1Z5'], 
            ['business_name' => 'Reliance Digital', 'pan_number' => 'AAAAA1234B', 'status' => 'Approved', 'tier' => 'Gold']
        );
        Store::firstOrCreate(
            ['merchant_id' => $reliance->id, 'name' => 'Reliance Digital - Andheri'], 
            ['address' => 'Andheri East, Mumbai', 'status' => 'active']
        );
        User::firstOrCreate(
            ['email' => 'reliance@finz.test'], 
            ['name' => 'Reliance Admin', 'password' => Hash::make('password'), 'role' => 'merchant_admin', 'merchant_id' => $reliance->id, 'is_active' => true]
        );

        $vijay = Merchant::firstOrCreate(
            ['gst_number' => '27BBBBB5678C1Z5'], 
            ['business_name' => 'Vijay Sales', 'pan_number' => 'BBBBB5678C', 'status' => 'Approved', 'tier' => 'Silver']
        );
        Store::firstOrCreate(
            ['merchant_id' => $vijay->id, 'name' => 'Vijay Sales - Bandra'], 
            ['address' => 'Bandra West, Mumbai', 'status' => 'active']
        );
        User::firstOrCreate(
            ['email' => 'vijay@finz.test'], 
            ['name' => 'Vijay Admin', 'password' => Hash::make('password'), 'role' => 'merchant_admin', 'merchant_id' => $vijay->id, 'is_active' => true]
        );
    }
}
