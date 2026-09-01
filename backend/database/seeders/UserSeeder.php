<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'Store Admin',
            'email' => 'admin@organicstore.test',
            'password' => $password,
            'role' => 'admin',
            'status' => 'active',
            'phone' => '09170000001',
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => 'Inventory Staff',
            'email' => 'staff@organicstore.test',
            'password' => $password,
            'role' => 'staff',
            'status' => 'active',
            'phone' => '09170000002',
            'email_verified_at' => now(),
        ]);

        $customers = [
            ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '09171234501'],
            ['name' => 'John Dela Cruz', 'email' => 'john@example.com', 'phone' => '09171234502'],
            ['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '09171234503'],
            ['name' => 'Miguel Garcia', 'email' => 'miguel@example.com', 'phone' => '09171234504'],
            ['name' => 'Liza Mendoza', 'email' => 'liza@example.com', 'phone' => '09171234505'],
        ];

        foreach ($customers as $customer) {
            $user = User::create([
                'name' => $customer['name'],
                'email' => $customer['email'],
                'password' => $password,
                'role' => 'customer',
                'status' => 'active',
                'phone' => $customer['phone'],
                'email_verified_at' => now(),
            ]);

            Cart::create(['user_id' => $user->id, 'status' => 'active']);
            Wishlist::create(['user_id' => $user->id]);
        }
    }
}
