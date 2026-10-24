<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('settings')->insertOrIgnore([
            'id' => 1, 'name' => 'Torque Workshop', 'tagline' => 'Expert care. Every mile.',
            'email' => 'hello@torque.example', 'phone' => '+63 917 555 0142', 'address' => '123 Service Avenue, Quezon City, Philippines',
            'currency' => 'PHP', 'tax_basis_points' => 1200, 'invoice_footer' => 'Thank you for trusting us with your vehicle. Please keep this invoice for your records.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
