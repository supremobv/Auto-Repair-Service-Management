<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Resources;
use App\Support\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        Access::check('orders.read');
        $r->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|string|max:40']);
        $query = DB::table('work_orders as w')->join('vehicles as v', 'v.id', '=', 'w.vehicle_id')->join('customers as c', 'c.id', '=', 'v.customer_id')->leftJoin('users as u', 'u.id', '=', 'w.technician_id')->select('w.*', 'v.plate', 'v.make', 'v.model', 'c.name as customer_name', 'u.name as technician_name');
        if (auth()->user()->role === 'technician') {
            $query->where('w.technician_id', auth()->id());
        }
        if ($r->filled('q')) {
            $query->where(fn ($q) => $q->where('w.number', 'like', '%'.$r->q.'%')->orWhere('c.name', 'like', '%'.$r->q.'%')->orWhere('v.plate', 'like', '%'.$r->q.'%'));
        }
        if ($r->filled('status')) {
            $query->where('w.status', $r->status);
        }
        $orders = $query->orderByDesc('w.id')->paginate(15)->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create()
    {
        Access::check('orders');

        return view('orders.create', ['options' => Resources::options()]);
    }

    public function store(Request $r)
    {
        Access::check('orders');
        $data = $r->validate(['vehicle_id' => 'required|exists:vehicles,id', 'technician_id' => 'nullable|exists:users,id', 'concern' => 'required|string|max:5000', 'priority' => 'required|in:normal,high,urgent', 'due_date' => 'nullable|date', 'mileage' => 'required|integer|min:0|max:10000000']);
        if (! empty($data['technician_id']) && ! DB::table('users')->where('id', $data['technician_id'])->where('active', true)->where('role', 'technician')->exists()) {
            throw ValidationException::withMessages(['technician_id' => 'Select an active technician.']);
        }
        $id = DB::transaction(function () use ($data) {
            $id = DB::table('work_orders')->insertGetId([...$data, 'number' => 'WO-'.strtoupper(Str::random(8)), 'status' => 'estimate', 'tax_basis_points' => DB::table('settings')->value('tax_basis_points') ?? 0, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('vehicles')->where('id', $data['vehicle_id'])->update(['mileage' => $data['mileage'], 'updated_at' => now()]);
            Workshop::audit('Created work order', '#'.$id);

            return $id;
        });

        return redirect('/orders/'.$id)->with('success', 'Work order created. Add labor and parts to prepare the estimate.');
    }

    public function show(int $id)
    {
        Access::check('orders.read');
        $order = $this->order($id);
        $vehicle = DB::table('vehicles')->find($order->vehicle_id);
        $customer = DB::table('customers')->find($vehicle->customer_id);
        $items = DB::table('order_items')->where('work_order_id', $id)->get();
        $totals = Workshop::totals($id);
        $invoice = DB::table('invoices')->where('work_order_id', $id)->first();
        $parts = DB::table('parts')->orderBy('name')->get();
        $services = DB::table('services')->orderBy('name')->get();
        $technician = DB::table('users')->find($order->technician_id);
        $technicians = DB::table('users')->where('role', 'technician')->where('active', true)->orderBy('name')->get();

        return view('orders.show', compact('order', 'vehicle', 'customer', 'items', 'totals', 'invoice', 'parts', 'services', 'technician', 'technicians'));
    }

    public function assign(Request $r, int $id)
    {
        Access::check('orders');
        $data = $r->validate(['technician_id' => 'nullable|exists:users,id', 'priority' => 'required|in:normal,high,urgent', 'due_date' => 'nullable|date']);
        DB::transaction(function () use ($id, $data) {
            $order = $this->order($id, true);
            if (in_array($order->status, ['completed', 'invoiced', 'cancelled'])) {
                throw ValidationException::withMessages(['order' => 'Closed work orders cannot be reassigned.']);
            }
            if (! empty($data['technician_id']) && ! DB::table('users')->where('id', $data['technician_id'])->where('role', 'technician')->where('active', true)->exists()) {
                throw ValidationException::withMessages(['technician_id' => 'Select an active technician.']);
            }
            DB::table('work_orders')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            Workshop::audit('Updated job assignment', $order->number);
        });

        return back()->with('success', 'Job assignment updated.');
    }

    private function order(int $id, bool $lock = false)
    {
        $q = DB::table('work_orders')->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        } $order = $q->first();
        abort_unless($order, 404);
        if (auth()->user()->role === 'technician') {
            abort_unless($order->technician_id === auth()->id(), 403);
        }

        return $order;
    }

    public function item(Request $r, int $id)
    {
        Access::check('orders');
        $data = $r->validate(['part_id' => 'nullable|exists:parts,id', 'service_id' => 'nullable|exists:services,id', 'description' => 'nullable|string|max:200', 'quantity' => 'required|integer|min:1|max:1000', 'price' => 'nullable|decimal:0,2|min:0|max:1000000']);
        DB::transaction(function () use ($id, $data) {
            $order = $this->order($id, true);
            if ($order->status !== 'estimate') {
                throw ValidationException::withMessages(['item' => 'Only draft estimates can be edited.']);
            }
            $part = ! empty($data['part_id']) ? DB::table('parts')->find($data['part_id']) : null;
            $service = ! empty($data['service_id']) ? DB::table('services')->find($data['service_id']) : null;
            if ($part && $service) {
                throw ValidationException::withMessages(['item' => 'Choose a part or a service, one at a time.']);
            }
            $description = $part?->name ?? $service?->name ?? ($data['description'] ?? null);
            $price = $part?->price_cents ?? $service?->price_cents ?? (isset($data['price']) ? Workshop::cents($data['price']) : null);
            if (! $description || $price === null) {
                throw ValidationException::withMessages(['description' => 'Choose a catalog item, or enter a description and price.']);
            }
            DB::table('order_items')->insert(['work_order_id' => $id, 'part_id' => $part?->id, 'description' => $description, 'quantity' => $data['quantity'], 'unit_cents' => $price, 'created_at' => now(), 'updated_at' => now()]);
            Workshop::audit('Added estimate item', $order->number);
        });

        return back()->with('success', 'Line item added.');
    }

    public function removeItem(int $id, int $item)
    {
        Access::check('orders');
        DB::transaction(function () use ($id, $item) {
            $order = $this->order($id, true);
            if ($order->status !== 'estimate') {
                throw ValidationException::withMessages(['item' => 'Approved estimates cannot be changed.']);
            }
            abort_unless(DB::table('order_items')->where('work_order_id', $id)->where('id', $item)->delete(), 404);
            Workshop::audit('Removed estimate item', $order->number);
        });

        return back()->with('success', 'Line item removed.');
    }

    public function update(Request $r, int $id)
    {
        Access::check('orders.work');
        $data = $r->validate(['diagnosis' => 'nullable|string|max:10000', 'inspection' => 'nullable|string|max:10000']);
        DB::transaction(function () use ($id, $data) {
            $order = $this->order($id, true);
            if (in_array($order->status, ['invoiced', 'cancelled'])) {
                throw ValidationException::withMessages(['order' => 'Closed work orders cannot be edited.']);
            }
            DB::table('work_orders')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            Workshop::audit('Updated inspection', $order->number);
        });

        return back()->with('success', 'Inspection and diagnosis saved.');
    }

    public function transition(Request $r, int $id)
    {
        $data = $r->validate(['status' => 'required|in:approved,in_progress,completed,cancelled', 'approval_name' => 'nullable|string|max:150']);
        $ability = in_array($data['status'], ['in_progress', 'completed']) ? 'orders.work' : 'orders';
        Access::check($ability);
        DB::transaction(function () use ($id, $data) {
            $order = $this->order($id, true);
            $next = ['estimate' => ['approved', 'cancelled'], 'approved' => ['in_progress', 'cancelled'], 'in_progress' => ['completed'], 'completed' => [], 'invoiced' => [], 'cancelled' => []];
            if (! in_array($data['status'], $next[$order->status] ?? [])) {
                throw ValidationException::withMessages(['status' => 'This status change is not available. Refresh the page and try again.']);
            }
            $update = ['status' => $data['status'], 'updated_at' => now()];
            if ($data['status'] === 'approved') {
                if (empty($data['approval_name'])) {
                    throw ValidationException::withMessages(['approval_name' => 'Record the name of the customer who authorized this estimate.']);
                }
                if (! DB::table('order_items')->where('work_order_id', $id)->exists()) {
                    throw ValidationException::withMessages(['order' => 'Add at least one line item before approval.']);
                }
                $update['approval_name'] = $data['approval_name'];
                $update['approved_at'] = now();
            }
            if ($data['status'] === 'in_progress') {
                $parts = DB::table('order_items')->where('work_order_id', $id)->whereNotNull('part_id')->select('part_id', DB::raw('SUM(quantity) as required_quantity'))->groupBy('part_id')->orderBy('part_id')->get();
                foreach ($parts as $item) {
                    $part = DB::table('parts')->where('id', $item->part_id)->lockForUpdate()->first();
                    if ($part->stock < $item->required_quantity) {
                        throw ValidationException::withMessages(['stock' => 'Not enough stock for '.$part->name.'. Receive stock before starting the job.']);
                    }
                    DB::table('parts')->where('id', $part->id)->decrement('stock', $item->required_quantity);
                    DB::table('stock_movements')->insert(['part_id' => $part->id, 'user_id' => auth()->id(), 'quantity' => -$item->required_quantity, 'reason' => $order->number, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('work_orders')->where('id', $id)->update($update);
            Workshop::audit('Status: '.$data['status'], $order->number);
        }, 3);

        return back()->with('success', 'Work order status updated.');
    }

    public function invoice(Request $r, int $id)
    {
        Access::check('billing');
        $data = $r->validate(['due_date' => 'required|date']);
        $invoiceId = DB::transaction(function () use ($id, $data) {
            $order = $this->order($id, true);
            if ($order->status !== 'completed') {
                throw ValidationException::withMessages(['order' => 'Complete the work order before issuing an invoice.']);
            }
            [$sub,$tax,$total] = Workshop::totals($id);
            $vehicle = DB::table('vehicles')->find($order->vehicle_id);
            $customer = DB::table('customers')->find($vehicle->customer_id);
            $invoiceId = DB::table('invoices')->insertGetId(['number' => 'INV-'.strtoupper(Str::random(8)), 'work_order_id' => $id, 'subtotal_cents' => $sub, 'tax_cents' => $tax, 'total_cents' => $total, 'paid_cents' => 0, 'due_date' => $data['due_date'], 'business_snapshot' => json_encode(DB::table('settings')->first()), 'customer_snapshot' => json_encode([...((array) $customer), 'vehicle' => $vehicle]), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('work_orders')->where('id',$id)->update(['status' => 'invoiced', 'updated_at' => now()]);
            Workshop::audit('Issued invoice',$order->number);

            return $invoiceId;
        });

        return redirect('/invoices/'.$invoiceId)->with('success','Invoice issued.');
    }
}
