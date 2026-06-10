<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\Tariff;
use App\Models\User;
use App\Models\VendingKey;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (['admin', 'vendor', 'viewer'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@local'],
            ['name' => 'Admin', 'password' => Hash::make('password')],
        );
        $admin->assignRole('admin');

        $sg = SupplyGroup::updateOrCreate(
            ['code' => '123456'],
            ['name' => 'Demo Supply Group', 'utility' => 'Demo Utility', 'region' => 'Demo Region'],
        );

        $vk = VendingKey::updateOrCreate(
            ['name' => 'VUDK_DEMO_DKGA02'],
            [
                'supply_group_id'      => $sg->id,
                'key_type'             => 2,
                'tariff_index'         => '01',
                'key_revision_number'  => 1,
                'key_expiry_number'    => 255,
                'algorithm'            => 'DKGA02',
                'encryption_algorithm' => 'EA07',
                'base_date'            => 1993,
                'vudk_blob'            => 'abababababababab', // 8-byte demo VUDK
                'is_active'            => true,
            ],
        );

        $tariff = Tariff::updateOrCreate(
            ['supply_group_id' => $sg->id, 'name' => 'Domestic A'],
            ['code' => 'D-A', 'rate_per_kwh' => 2.5500, 'currency' => 'ZMW', 'max_power_limit_w' => 6000, 'is_active' => true],
        );

        $cust = Customer::updateOrCreate(
            ['account_number' => 'ACC-000001'],
            ['name' => 'Demo Customer', 'phone' => '+260 977 000 001', 'email' => 'demo@local', 'city' => 'Lusaka'],
        );

        Meter::updateOrCreate(
            ['pan' => '600727000000000009'],
            [
                'iin'                   => '600727',
                'iain'                  => '00000000000',
                'manufacturer_code'     => 'DEMO',
                'decoder_serial_number' => '00000001',
                'supply_group_id'       => $sg->id,
                'vending_key_id'        => $vk->id,
                'tariff_id'             => $tariff->id,
                'customer_id'           => $cust->id,
                'location'              => 'Demo Site',
                'is_active'             => true,
            ],
        );
    }
}

