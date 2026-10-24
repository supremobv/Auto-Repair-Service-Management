<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'must_change_password' => false]);
    }

    private function vehicle(): int
    {
        $stamp = ['created_at' => now(), 'updated_at' => now()];
        $customer = DB::table('customers')->insertGetId([...$stamp, 'name' => 'Test Customer', 'phone' => '0123456789']);

        return DB::table('vehicles')->insertGetId([...$stamp, 'customer_id' => $customer, 'plate' => Str::random(8), 'make' => 'Toyota', 'model' => 'Vios', 'year' => 2022, 'mileage' => 20000]);
    }

    private function order(?int $tech = null): int
    {
        return DB::table('work_orders')->insertGetId(['number' => 'WO-'.Str::random(8), 'vehicle_id' => $this->vehicle(), 'technician_id' => $tech, 'status' => 'estimate', 'priority' => 'normal', 'concern' => 'Check brakes', 'mileage' => 20000, 'tax_basis_points' => 1200, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function part(int $stock = 5): int
    {
        return DB::table('parts')->insertGetId(['sku' => Str::random(8), 'name' => 'Brake pads', 'price_cents' => 10001, 'cost_cents' => 5000, 'stock' => $stock, 'reorder_level' => 2, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->user());
        foreach (['/', '/orders', '/orders/create', '/invoices', '/settings', '/staff', '/audit', '/reports', '/help', '/stock-movements'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['customers', 'vehicles', 'appointments', 'inventory', 'services', 'suppliers'] as $resource) {
            $this->get('/manage/'.$resource)->assertOk();
            $this->get('/manage/'.$resource.'/create')->assertOk();
        }
    }

    public function test_roles_enforce_server_permissions(): void
    {
        $this->actingAs($this->user('advisor'));
        $this->get('/settings')->assertForbidden();
        $this->post('/settings', [])->assertForbidden();
        $this->get('/staff')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->post('/inventory/1/stock', [])->assertForbidden();
        $this->post('/invoices/1/payments', [])->assertForbidden();
        $this->get('/manage/inventory')->assertOk();
        $this->get('/manage/inventory/create')->assertForbidden();
        $this->actingAs($this->user('cashier'));
        $this->post('/orders', [])->assertForbidden();
        $this->get('/invoices')->assertOk();
    }

    public function test_technician_can_only_access_assigned_jobs(): void
    {
        $a = $this->user('technician');
        $b = $this->user('technician');
        $own = $this->order($a->id);
        $other = $this->order($b->id);
        $this->actingAs($a);
        $this->get('/orders/'.$own)->assertOk();
        $this->get('/orders/'.$other)->assertForbidden();
        $this->put('/orders/'.$other, ['diagnosis' => 'Trying to change another job'])->assertForbidden();
        $this->put('/orders/'.$own, ['diagnosis' => 'Brakes worn', 'inspection' => 'Tires OK'])->assertRedirect();
        $this->assertDatabaseHas('work_orders', ['id' => $own, 'diagnosis' => 'Brakes worn']);
        $this->post('/orders/'.$own.'/items', [])->assertForbidden();
    }

    public function test_complete_repair_invoice_and_payment_flow(): void
    {
        $this->actingAs($this->user());
        $order = $this->order();
        $part = $this->part();
        $this->post('/orders/'.$order.'/items', ['part_id' => $part, 'quantity' => 2, 'price' => '0.01'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', ['work_order_id' => $order, 'unit_cents' => 10001]);
        $this->post('/orders/'.$order.'/status', ['status' => 'approved', 'approval_name' => 'Test Customer'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$order.'/items', ['quantity' => 1, 'description' => 'Extra', 'price' => 1])->assertSessionHasErrors();
        $this->post('/orders/'.$order.'/status', ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('parts', ['id' => $part, 'stock' => 3]);
        $this->post('/orders/'.$order.'/status', ['status' => 'in_progress'])->assertSessionHasErrors();
        $this->assertDatabaseHas('parts', ['id' => $part, 'stock' => 3]);
        $this->post('/orders/'.$order.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$order.'/invoice', ['due_date' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $invoice = DB::table('invoices')->first();
        $this->assertSame(20002, (int) $invoice->subtotal_cents);
        $this->assertSame(2400, (int) $invoice->tax_cents);
        $this->assertSame(22402, (int) $invoice->total_cents);
        $this->post('/orders/'.$order.'/invoice', ['due_date' => today()->toDateString()])->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('224.02');
        $key = (string) Str::uuid();
        $payment = ['amount' => '100.00', 'method' => 'cash', 'idempotency_key' => $key];
        $this->post('/invoices/'.$invoice->id.'/payments', $payment)->assertSessionHasNoErrors();
        $this->post('/invoices/'.$invoice->id.'/payments', $payment)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->post('/invoices/'.$invoice->id.'/payments', ['amount' => '999.00', 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->post('/invoices/'.$invoice->id.'/payments', ['amount' => '124.02', 'method' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'paid_cents' => 22402]);
    }

    public function test_stock_failure_rolls_back_whole_order(): void
    {
        $this->actingAs($this->user());
        $order = $this->order();
        $a = $this->part(5);
        $b = $this->part(0);
        foreach ([$a, $b] as $part) {
            $this->post('/orders/'.$order.'/items', ['part_id' => $part, 'quantity' => 1])->assertSessionHasNoErrors();
        }
        $this->post('/orders/'.$order.'/status', ['status' => 'approved', 'approval_name' => 'Test Customer']);
        $this->post('/orders/'.$order.'/status', ['status' => 'in_progress'])->assertSessionHasErrors('stock');
        $this->assertDatabaseHas('parts', ['id' => $a, 'stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('work_orders', ['id' => $order, 'status' => 'approved']);
        $this->post('/inventory/'.$b.'/stock', ['quantity' => 10, 'reason' => 'Delivery PO 123'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$order.'/status', ['status' => 'in_progress'])->assertSessionHasNoErrors();
    }

    public function test_negative_stock_is_rejected(): void
    {
        $this->actingAs($this->user());
        $part = $this->part(1);
        $this->post('/inventory/'.$part.'/stock', ['quantity' => -2, 'reason' => 'Correction'])->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('parts', ['id' => $part, 'stock' => 1]);
    }

    public function test_appointment_conflicts_and_validation(): void
    {
        $this->actingAs($this->user());
        $tech = $this->user('technician');
        $data = ['vehicle_id' => $this->vehicle(), 'technician_id' => $tech->id, 'service' => 'Maintenance', 'starts_at' => '2026-11-01T09:00', 'ends_at' => '2026-11-01T10:00', 'status' => 'scheduled'];
        $this->post('/manage/appointments', $data)->assertSessionHasNoErrors();
        $this->post('/manage/appointments', [...$data, 'starts_at' => '2026-11-01T09:30', 'ends_at' => '2026-11-01T10:30'])->assertSessionHasErrors('starts_at');
        $this->post('/manage/appointments', [...$data, 'starts_at' => '2026-11-01T10:00', 'ends_at' => '2026-11-01T11:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_referenced_customer_is_protected(): void
    {
        $this->actingAs($this->user());
        $vehicle = $this->vehicle();
        $customer = DB::table('vehicles')->find($vehicle)->customer_id;
        $this->delete('/manage/customers/'.$customer)->assertSessionHasErrors();
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_untrusted_text_is_escaped(): void
    {
        $this->actingAs($this->user());
        $this->post('/manage/customers', ['name' => '<script>alert(1)</script>', 'phone' => '123']);
        $this->get('/manage/customers')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/manage/not-a-table')->assertNotFound();
    }

    public function test_inactive_and_temporary_password_accounts_are_blocked(): void
    {
        $inactive = $this->user();
        $inactive->update(['active' => false]);
        $this->actingAs($inactive)->get('/')->assertRedirect('/login');
        $temp = $this->user();
        $temp->update(['must_change_password' => true]);
        $this->actingAs($temp)->get('/settings')->assertRedirect('/password');
        $this->get('/password')->assertOk();
    }

    public function test_admin_cannot_demote_self(): void
    {
        $u = $this->user();
        $this->actingAs($u)->put('/staff/'.$u->id, ['name' => $u->name, 'email' => $u->email, 'role' => 'cashier', 'active' => 1])->assertSessionHasErrors('role');
        $this->assertDatabaseHas('users', ['id' => $u->id, 'role' => 'admin']);
    }

    public function test_branding_and_currency_lock(): void
    {
        $this->actingAs($this->user());
        $data = ['name' => 'New Workshop', 'currency' => 'SGD', 'tax_rate' => '9.00', 'tagline' => 'We care'];
        $this->post('/settings', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['name' => 'New Workshop', 'currency' => 'SGD', 'tax_basis_points' => 900]);
        $this->order();
        $this->post('/settings', [...$data, 'currency' => 'USD'])->assertSessionHasErrors('currency');
    }

    public function test_security_headers_and_login_rate_limit(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy');
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'missing@example.com', 'password' => 'incorrect']);
        }
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_password_change_requires_current_password_and_strength(): void
    {
        $u = $this->user();
        $u->update(['password' => 'OldPassword!2026', 'must_change_password' => true]);
        $this->actingAs($u);
        $this->post('/password', ['current_password' => 'wrong', 'password' => 'NewPassword!2026', 'password_confirmation' => 'NewPassword!2026'])->assertSessionHasErrors('current_password');
        $this->post('/password', ['current_password' => 'OldPassword!2026', 'password' => 'NewPassword!2026', 'password_confirmation' => 'NewPassword!2026'])->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertFalse($u->fresh()->must_change_password);
    }

    public function test_assignment_can_be_changed_only_by_workshop_staff(): void
    {
        $admin = $this->user();
        $tech = $this->user('technician');
        $other = $this->user('technician');
        $order = $this->order($tech->id);
        $data = ['technician_id' => $other->id, 'priority' => 'urgent', 'due_date' => '2026-12-01'];
        $this->actingAs($tech)->put('/orders/'.$order.'/assignment', $data)->assertForbidden();
        $this->actingAs($admin)->put('/orders/'.$order.'/assignment', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_orders', ['id' => $order, 'technician_id' => $other->id, 'priority' => 'urgent']);
        $this->actingAs($tech)->get('/orders/'.$order)->assertForbidden();
        $this->get('/vehicles/'.DB::table('work_orders')->find($order)->vehicle_id.'/history')->assertForbidden();
    }

    public function test_invoice_preserves_original_business_customer_and_vehicle(): void
    {
        $this->actingAs($this->user());
        $order = $this->order();
        DB::table('work_orders')->where('id', $order)->update(['status' => 'completed']);
        DB::table('order_items')->insert(['work_order_id' => $order, 'description' => 'Inspection', 'quantity' => 1, 'unit_cents' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/orders/'.$order.'/invoice', ['due_date' => '2026-12-01'])->assertSessionHasNoErrors();
        $invoice = DB::table('invoices')->first();
        $vehicle = DB::table('work_orders')->find($order)->vehicle_id;
        DB::table('settings')->update(['name' => 'Renamed Business']);
        DB::table('customers')->update(['name' => 'Renamed Customer']);
        DB::table('vehicles')->where('id', $vehicle)->update(['model' => 'Changed Model']);
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Test Customer')->assertSee('Toyota Vios')->assertDontSee('Changed Model');
        $snapshot = json_decode($invoice->business_snapshot);
        $this->assertSame('Torque Workshop', $snapshot->name);
    }

    public function test_logo_upload_is_reencoded_and_executable_upload_rejected(): void
    {
        $this->actingAs($this->user());
        Storage::fake('public');
        $data = ['name' => 'Workshop', 'currency' => 'PHP', 'tax_rate' => '12.00'];
        $this->post('/settings', [...$data, 'logo' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->post('/settings', [...$data, 'logo' => UploadedFile::fake()->image('brand.jpg', 100, 100)])->assertSessionHasNoErrors();
        $path = DB::table('settings')->value('logo');
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
        $bytes = Storage::disk('public')->get($path);
        $this->assertSame("\x89PNG\r\n\x1a\n",substr($bytes,0,8));
    }

    public function test_csrf_middleware_rejects_missing_token_outside_test_bypass(): void
    {
        // Laravel intentionally skips CSRF when APP_ENV=testing; enable the real check for this request.
        $this->app->instance('env','local');
        $this->post('/login',['email' => 'admin@example.com', 'password' => 'irrelevant'])->assertStatus(419);
    }
}
