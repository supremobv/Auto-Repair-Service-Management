<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Resources;
use App\Support\Workshop;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    public function index(Request $r, string $resource)
    {
        $config = Resources::get($resource);
        Access::check($config['ability'].'.read');
        $table = $config['table'] ?? $resource;
        $r->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|string|max:40']);
        $query = DB::table($table);
        if ($r->filled('q')) {
            $query->where(function ($q) use ($config, $r, $table) {
                foreach ($config['search'] as $field) {
                    $q->orWhere($table.'.'.$field, 'like', '%'.$r->q.'%');
                }
            });
        }
        if ($resource === 'appointments') {
            $query->leftJoin('vehicles', 'vehicles.id', '=', 'appointments.vehicle_id')->leftJoin('users', 'users.id', '=', 'appointments.technician_id')->select('appointments.*', 'vehicles.plate as vehicle_label', 'users.name as technician_name');
            if ($r->filled('status')) {
                $query->where('appointments.status', $r->status);
            }
            $query->orderByDesc('appointments.starts_at');
        } else {
            $query->orderByDesc($table.'.id');
        }
        if ($resource === 'inventory' && $r->boolean('low')) {
            $query->whereColumn('stock', '<=', 'reorder_level');
        }
        $rows = $query->paginate(15)->withQueryString();

        return view('resources.index', compact('resource', 'config', 'rows'));
    }

    public function form(string $resource, ?int $id = null)
    {
        $config = Resources::get($resource);
        Access::check($config['ability']);
        $record = $id ? DB::table($config['table'] ?? $resource)->find($id) : null;
        if ($id) {
            abort_unless($record, 404);
        }

        return view('resources.form', ['resource' => $resource, 'config' => $config, 'record' => $record, 'options' => Resources::options()]);
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        $config = Resources::get($resource);
        Access::check($config['ability']);
        $table = $config['table'] ?? $resource;
        if ($id) {
            abort_unless(DB::table($table)->find($id), 404);
        }
        $rules = [];
        foreach ($config['fields'] as $key => $field) {
            $rules[$key] = $field[2];
        }
        foreach (['plate', 'vin', 'sku'] as $key) {
            if (isset($rules[$key])) {
                $rules[$key] = [$rules[$key], Rule::unique($table, $key)->ignore($id)];
            }
        }
        $data = $r->validate($rules);
        if ($resource === 'appointments') {
            $data['starts_at'] = Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s');
            $data['ends_at'] = Carbon::parse($data['ends_at'])->format('Y-m-d H:i:s');
        }
        foreach (['price', 'cost'] as $key) {
            if (isset($data[$key])) {
                $data[$key.'_cents'] = Workshop::cents($data[$key]);
                unset($data[$key]);
            }
        }
        if ($resource === 'vehicles') {
            $data['plate'] = strtoupper(trim($data['plate']));
            $data['vin'] = isset($data['vin']) ? strtoupper(trim($data['vin'])) : null;
            // Keep historical ownership stable when service records already exist.
            if ($id && DB::table('vehicles')->where('id', $id)->value('customer_id') != $data['customer_id'] && DB::table('work_orders')->where('vehicle_id', $id)->exists()) {
                throw ValidationException::withMessages(['customer_id' => 'A vehicle with service history cannot be transferred here. Create a new customer vehicle record after archiving its history externally.']);
            }
        }
        DB::transaction(function () use ($data, $id, $table, $resource) {
            if ($resource === 'appointments' && ! empty($data['technician_id'])) {
                $tech = DB::table('users')->where('id', $data['technician_id'])->lockForUpdate()->first();
                if (! $tech || ! $tech->active || $tech->role !== 'technician') {
                    throw ValidationException::withMessages(['technician_id' => 'Select an active technician.']);
                }
                if (! in_array($data['status'], ['cancelled', 'no_show', 'completed']) && DB::table('appointments')->where('technician_id', $tech->id)->when($id, fn ($q) => $q->where('id', '!=', $id))->whereIn('status', ['scheduled', 'checked_in'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) {
                    throw ValidationException::withMessages(['starts_at' => 'This technician already has an appointment during this time.']);
                }
            }
            $data['updated_at'] = now();
            if ($id) {
                DB::table($table)->where('id', $id)->update($data);
            } else {
                $id = DB::table($table)->insertGetId([...$data, 'created_at' => now()]);
            }
            Workshop::audit('Saved '.$resource, '#'.$id);
        });

        return redirect('/manage/'.$resource)->with('success', ucfirst($config['singular']).' saved.');
    }

    public function delete(string $resource, int $id)
    {
        $config = Resources::get($resource);
        Access::check($config['ability']);
        abort_unless(DB::table($config['table'] ?? $resource)->find($id), 404);
        try {
            DB::transaction(function () use ($resource, $id, $config) {
                if ($resource === 'inventory' && (DB::table('parts')->where('id', $id)->value('stock') > 0 || DB::table('stock_movements')->where('part_id', $id)->exists())) {
                    throw ValidationException::withMessages(['record' => 'Parts with stock or stock history cannot be deleted.']);
                }
                DB::table($config['table'] ?? $resource)->where('id', $id)->delete();
                Workshop::audit('Deleted '.$resource, '#'.$id);
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23503'])) {
                return back()->withErrors(['record' => 'This record is linked to workshop history and cannot be deleted.']);
            }
            throw $e;
        }

        return back()->with('success', 'Record deleted.');
    }

    public function history(int $id)
    {
        Access::check('vehicles.read');
        $vehicle = DB::table('vehicles')->find($id);
        abort_unless($vehicle, 404);
        $customer = DB::table('customers')->find($vehicle->customer_id);
        $orders = DB::table('work_orders')->where('vehicle_id', $id)->orderByDesc('id')->paginate(15);
        if (auth()->user()->role === 'technician') {
            abort_unless(DB::table('work_orders')->where('vehicle_id', $id)->where('technician_id', auth()->id())->exists(), 403);
            $orders = DB::table('work_orders')->where('vehicle_id', $id)->where('technician_id', auth()->id())->orderByDesc('id')->paginate(15);
        }

        return view('vehicles.history', compact('vehicle', 'customer', 'orders'));
    }

    public function stock(Request $r, int $id)
    {
        Access::check('inventory');
        $data = $r->validate(['quantity' => 'required|integer|not_in:0|between:-1000000,1000000', 'reason' => 'required|string|min:5|max:200']);
        DB::transaction(function () use ($id, $data) {
            $part = DB::table('parts')->where('id', $id)->lockForUpdate()->first();
            abort_unless($part, 404);
            if ($part->stock + $data['quantity'] < 0) {
                throw ValidationException::withMessages(['quantity' => 'This adjustment would make stock negative.']);
            }
            DB::table('parts')->where('id', $id)->update(['stock' => $part->stock + $data['quantity'], 'updated_at' => now()]);
            DB::table('stock_movements')->insert([...$data, 'part_id' => $id, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            Workshop::audit('Adjusted stock','#'.$id.' · '.$data['quantity'].' · '.$data['reason']);
        });

        return back()->with('success','Stock adjustment recorded.');
    }
}
