<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo data is only available in local and testing environments.');
        }
        if (DB::table('customers')->exists() || User::exists()) {
            throw new \RuntimeException('Demo data requires an empty workshop database. Existing data was not changed.');
        }
        $this->call(DatabaseSeeder::class);
        DB::transaction(function () {
            $staff = [];
            foreach (['admin' => 'Alex Morgan', 'manager' => 'Jamie Reyes', 'advisor' => 'Sam Rivera', 'technician' => 'Miguel Santos', 'cashier' => 'Casey Cruz'] as $role => $name) {
                $staff[$role] = User::create(['name' => $name, 'email' => $role.'@torque.test', 'password' => 'TorqueDemo!2026', 'role' => $role, 'active' => true, 'must_change_password' => true]);
            }
            $tech2 = User::create(['name' => 'Daniel Lim', 'email' => 'daniel@torque.test', 'password' => 'TorqueDemo!2026', 'role' => 'technician', 'active' => true, 'must_change_password' => true]);
            $stamp = ['created_at' => now(), 'updated_at' => now()];
            $supplier = DB::table('suppliers')->insertGetId([...$stamp, 'name' => 'Metro Auto Supply', 'email' => 'orders@metro.example', 'phone' => '+63 2 8555 0112', 'address' => '45 Industrial Road, Manila']);
            foreach ([['OIL-5W30', 'Synthetic engine oil 5W-30', 38000, 55000, 3, 10], ['FLT-OIL', 'Premium oil filter', 16000, 28000, 4, 8], ['BRK-PAD', 'Ceramic brake pads (set)', 160000, 240000, 2, 4], ['FLT-AIR', 'Engine air filter', 35000, 65000, 14, 5], ['SPK-IR', 'Iridium spark plug', 45000, 75000, 22, 8]] as [$sku,$name,$cost,$price,$stock,$reorder]) {
                $part = DB::table('parts')->insertGetId([...$stamp, 'sku' => $sku, 'name' => $name, 'supplier_id' => $supplier, 'cost_cents' => $cost, 'price_cents' => $price, 'stock' => $stock, 'reorder_level' => $reorder]);
                DB::table('stock_movements')->insert([...$stamp, 'part_id' => $part, 'user_id' => $staff['admin']->id, 'quantity' => $stock, 'reason' => 'Opening demo inventory']);
            }
            foreach ([['Oil & filter service', 'Oil and filter replacement labor', 60000, 45], ['Brake inspection', 'Inspection and brake system report', 45000, 45], ['Wheel alignment', 'Four-wheel alignment labor', 120000, 60], ['Full vehicle inspection', 'Multi-point safety inspection', 85000, 90]] as [$name,$desc,$price,$minutes]) {
                DB::table('services')->insert([...$stamp, 'name' => $name, 'description' => $desc, 'price_cents' => $price, 'duration_minutes' => $minutes]);
            }
            $customers = [['Isabella Garcia', '0917 555 0101', 'Toyota', 'Vios', 'NCR 4821', 2021], ['Ethan Tan', '0917 555 0102', 'Honda', 'Civic', 'ABC 2486', 2020], ['Olivia Reyes', '0917 555 0103', 'Ford', 'Ranger', 'NDA 7093', 2022], ['Lucas Mendoza', '0917 555 0104', 'Mitsubishi', 'Montero', 'DBA 5732', 2019], ['Sofia Lim', '0917 555 0105', 'Hyundai', 'Tucson', 'NCH 1958', 2023], ['Noah Santos', '0917 555 0106', 'Suzuki', 'Swift', 'NCT 6304', 2021], ['Amelia Cruz', '0917 555 0107', 'Toyota', 'Fortuner', 'NDB 8801', 2022], ['Liam Torres', '0917 555 0108', 'Honda', 'City', 'NCE 3342', 2020]];
            $statuses = ['in_progress', 'approved', 'in_progress', 'completed', 'estimate', 'approved', 'invoiced', 'invoiced'];
            $concerns = ['Periodic maintenance and oil change. Check fluid levels.', 'Squeaking brakes when slowing down. Inspect front brake pads.', 'Vehicle pulls to the left. Check wheel alignment.', 'Air conditioning not cooling well. Diagnose and service.', 'Engine warning light is on. Run diagnostic inspection.', 'Replace worn tires and check suspension.', 'Routine maintenance and filter replacement.', 'Full vehicle safety inspection before a long trip.'];
            foreach ($customers as $i => [$name,$phone,$make,$model,$plate,$year]) {
                $customer = DB::table('customers')->insertGetId([...$stamp, 'name' => $name, 'phone' => $phone, 'email' => strtolower(explode(' ', $name)[0]).'@example.com', 'address' => ($i + 1).' Sample Street, Quezon City']);
                $vehicle = DB::table('vehicles')->insertGetId([...$stamp, 'customer_id' => $customer, 'plate' => $plate, 'make' => $make, 'model' => $model, 'year' => $year, 'mileage' => 28000 + $i * 5200]);
                $status = $statuses[$i];
                $approved = ! in_array($status, ['estimate', 'cancelled']);
                $order = DB::table('work_orders')->insertGetId([...$stamp, 'number' => 'WO-'.(1041 + $i), 'vehicle_id' => $vehicle, 'technician_id' => $i % 2 ? $tech2->id : $staff['technician']->id, 'status' => $status, 'priority' => $i === 1 ? 'urgent' : ($i === 2 ? 'high' : 'normal'), 'concern' => $concerns[$i], 'diagnosis' => $i === 3 ? 'Refrigerant level checked; cooling performance restored.' : null, 'inspection' => $i === 3 ? 'Brakes: OK. Tires: OK. Fluids: topped up.' : null, 'due_date' => today()->addDays($i % 3)->toDateString(), 'mileage' => 28000 + $i * 5200, 'tax_basis_points' => 1200, 'approval_name' => $approved ? $name : null, 'approved_at' => $approved ? now()->subHours(3) : null]);
                DB::table('order_items')->insert([...$stamp, 'work_order_id' => $order, 'description' => $i % 2 ? 'Brake inspection & labor' : 'Periodic maintenance labor', 'quantity' => 1, 'unit_cents' => $i % 2 ? 145000 : 85000]);
                if ($status === 'invoiced') {
                    $sub = $i % 2 ? 145000 : 85000;
                    $tax = (int) round($sub * .12);
                    $paid = $i === 6 ? $sub + $tax : 50000;
                    $invoice = DB::table('invoices')->insertGetId([...$stamp, 'number' => 'INV-'.(2025 + $i), 'work_order_id' => $order, 'subtotal_cents' => $sub, 'tax_cents' => $tax, 'total_cents' => $sub + $tax, 'paid_cents' => $paid, 'due_date' => today()->addDays($i === 6 ? 7 : -2)->toDateString(), 'business_snapshot' => json_encode(DB::table('settings')->first()), 'customer_snapshot' => json_encode(DB::table('customers')->find($customer))]);
                    DB::table('payments')->insert([...$stamp, 'invoice_id' => $invoice, 'user_id' => $staff['cashier']->id, 'amount_cents' => $paid, 'method' => 'cash', 'reference' => 'Demo receipt', 'idempotency_key' => (string) Str::uuid()]);
                }
                if ($i < 3) {
                    DB::table('appointments')->insert([...$stamp, 'vehicle_id' => $vehicle, 'technician_id' => $i % 2 ? $tech2->id : $staff['technician']->id, 'service' => ['Oil & filter service', 'Brake inspection', 'Wheel alignment'][$i], 'starts_at' => today()->setHour(9 + $i * 2), 'ends_at' => today()->setHour(10 + $i * 2), 'status' => $i === 0 ? 'checked_in' : 'scheduled']);
                }
            }
            DB::table('audit_logs')->insert([...$stamp, 'user_id' => $staff['admin']->id, 'action' => 'Prepared demo workspace', 'subject' => 'Sample data', 'ip' => '127.0.0.1']);
        });
    }
}
