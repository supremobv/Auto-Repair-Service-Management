<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function index(Request $r)
    {
        Access::check('billing.read');
        $r->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|in:paid,outstanding,overdue']);
        $q = DB::table('invoices as i')->join('work_orders as w', 'w.id', '=', 'i.work_order_id')->join('vehicles as v', 'v.id', '=', 'w.vehicle_id')->join('customers as c', 'c.id', '=', 'v.customer_id')->select('i.*', 'c.name as customer_name', 'v.plate');
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('i.number', 'like', '%'.$r->q.'%')->orWhere('c.name', 'like', '%'.$r->q.'%'));
        }
        if ($r->status === 'paid') {
            $q->whereColumn('paid_cents', '>=', 'total_cents');
        }
        if (in_array($r->status, ['outstanding', 'overdue'])) {
            $q->whereColumn('paid_cents', '<', 'total_cents');
        }
        if ($r->status === 'overdue') {
            $q->where('i.due_date', '<', today());
        }
        $invoices = $q->orderByDesc('i.id')->paginate(15)->withQueryString();

        return view('billing.index', compact('invoices'));
    }

    public function show(int $id)
    {
        Access::check('billing.read');
        $invoice = DB::table('invoices')->find($id);
        abort_unless($invoice, 404);
        $order = DB::table('work_orders')->find($invoice->work_order_id);
        $vehicle = DB::table('vehicles')->find($order->vehicle_id);
        $items = DB::table('order_items')->where('work_order_id', $order->id)->get();
        $payments = DB::table('payments')->where('invoice_id', $id)->orderByDesc('id')->get();
        $business = json_decode($invoice->business_snapshot);
        $customer = json_decode($invoice->customer_snapshot);
        $vehicle = $customer->vehicle ?? $vehicle;

        return view('billing.show', compact('invoice', 'order', 'vehicle', 'items', 'payments', 'business', 'customer'));
    }

    public function payment(Request $r, int $id)
    {
        Access::check('billing');
        $data = $r->validate(['amount' => 'required|decimal:0,2|min:0.01|max:1000000000', 'method' => 'required|in:cash,bank_transfer,card,ewallet', 'reference' => 'nullable|string|max:150', 'idempotency_key' => 'required|uuid']);
        DB::transaction(function () use ($id, $data) {
            $invoice = DB::table('invoices')->where('id', $id)->lockForUpdate()->first();
            abort_unless($invoice, 404);
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->invoice_id !== $id || $existing->amount_cents !== Workshop::cents($data['amount'])) {
                    throw ValidationException::withMessages(['payment' => 'This payment reference has already been used. Reload the page.']);
                }

                return;
            }
            $amount = Workshop::cents($data['amount']);
            if ($amount > $invoice->total_cents - $invoice->paid_cents) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the remaining invoice balance.']);
            }
            DB::table('payments')->insert(['invoice_id' => $id, 'user_id' => auth()->id(), 'amount_cents' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'idempotency_key' => $data['idempotency_key'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('invoices')->where('id', $id)->update(['paid_cents' => $invoice->paid_cents + $amount, 'updated_at' => now()]);
            Workshop::audit('Recorded payment', $invoice->number.' · '.Workshop::money($amount));
        }, 3);

        return back()->with('success','Payment recorded.');
    }
}
