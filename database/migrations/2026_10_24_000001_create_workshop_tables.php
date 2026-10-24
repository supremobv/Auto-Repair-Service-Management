<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('technician')->index();
            $t->boolean('active')->default(true);
            $t->boolean('must_change_password')->default(true);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone');
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('plate')->unique();
            $t->string('make');
            $t->string('model');
            $t->unsignedSmallInteger('year');
            $t->string('vin', 30)->nullable()->unique();
            $t->unsignedInteger('mileage')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
        });
        Schema::create('parts', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('cost_cents')->default(0);
            $t->unsignedBigInteger('price_cents');
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('reorder_level')->default(5);
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('price_cents');
            $t->unsignedInteger('duration_minutes')->default(60);
            $t->timestamps();
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at')->index();
            $t->dateTime('ends_at');
            $t->string('status')->default('scheduled')->index();
            $t->string('service');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status')->default('estimate')->index();
            $t->string('priority')->default('normal');
            $t->text('concern');
            $t->text('diagnosis')->nullable();
            $t->text('inspection')->nullable();
            $t->date('due_date')->nullable()->index();
            $t->unsignedInteger('mileage')->default(0);
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->string('approval_name')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_cents');
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('work_order_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('subtotal_cents');
            $t->unsignedBigInteger('tax_cents');
            $t->unsignedBigInteger('total_cents');
            $t->unsignedBigInteger('paid_cents')->default(0);
            $t->date('due_date');
            $t->json('business_snapshot');
            $t->json('customer_snapshot');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_cents');
            $t->string('method');
            $t->string('reference')->nullable();
            $t->string('idempotency_key', 36)->unique();
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('part_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->integer('quantity');
            $t->string('reason');
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('tagline')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('currency', 3)->default('PHP');
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->string('logo')->nullable();
            $t->text('invoice_footer')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject');
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'settings', 'stock_movements', 'payments', 'invoices', 'order_items', 'work_orders', 'appointments', 'services', 'parts', 'suppliers', 'vehicles', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'must_change_password']));
    }
};
