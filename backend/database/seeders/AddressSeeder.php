<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        $templates = [
            [
                'label' => 'Home', 'recipient' => 'Maria Santos', 'line1' => '12 Green Valley St', 'line2' => null,
                'city' => 'Quezon City', 'state' => 'Metro Manila', 'postal' => '1101', 'country' => 'Philippines',
            ],
            [
                'label' => 'Work', 'recipient' => 'John Dela Cruz', 'line1' => '88 Tech Park Ave', 'line2' => 'Unit 401',
                'city' => 'Makati', 'state' => 'Metro Manila', 'postal' => '1233', 'country' => 'Philippines',
            ],
            [
                'label' => 'Home', 'recipient' => 'Ana Reyes', 'line1' => '45 Sunset Boulevard', 'line2' => null,
                'city' => 'Taguig', 'state' => 'Metro Manila', 'postal' => '1630', 'country' => 'Philippines',
            ],
            [
                'label' => 'Home', 'recipient' => 'Miguel Garcia', 'line1' => '7 Orchard Road', 'line2' => 'Floor 2',
                'city' => 'Pasig', 'state' => 'Metro Manila', 'postal' => '1600', 'country' => 'Philippines',
            ],
            [
                'label' => 'Home', 'recipient' => 'Liza Mendoza', 'line1' => '23 Meadow Lane', 'line2' => null,
                'city' => 'Mandaluyong', 'state' => 'Metro Manila', 'postal' => '1550', 'country' => 'Philippines',
            ],
        ];

        foreach ($customers as $i => $customer) {
            $tpl = $templates[$i % count($templates)];
            Address::create([
                'user_id' => $customer->id,
                'label' => $tpl['label'],
                'recipient_name' => $tpl['recipient'],
                'recipient_phone' => $customer->phone,
                'address_line1' => $tpl['line1'],
                'address_line2' => $tpl['line2'],
                'city' => $tpl['city'],
                'state' => $tpl['state'],
                'postal_code' => $tpl['postal'],
                'country' => $tpl['country'],
                'is_default' => true,
            ]);

            Address::create([
                'user_id' => $customer->id,
                'label' => 'Other',
                'recipient_name' => $tpl['recipient'],
                'recipient_phone' => $customer->phone,
                'address_line1' => '101 Alternate St',
                'address_line2' => null,
                'city' => $tpl['city'],
                'state' => 'Metro Manila',
                'postal_code' => $tpl['postal'],
                'country' => 'Philippines',
                'is_default' => false,
            ]);
        }
    }
}
