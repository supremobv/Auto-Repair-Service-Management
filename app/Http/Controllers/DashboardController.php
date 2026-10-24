<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Access::check('dashboard');
        $base = DB::table('work_orders');
        if (auth()->user()->role === 'technician') {
            $base->where('technician_id', auth()->id());
        }
        $active = (clone $base)->whereIn('status', ['approved', 'in_progress'])->count();
        $ready = (clone $base)->where('status', 'completed')->count();
        $estimates = (clone $base)->where('status', 'estimate')->count();
        $revenue = Access::allows(auth()->user(), 'reports') ? DB::table('payments')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_cents') : null;
        $outstanding = Access::allows(auth()->user(), 'billing.read') ? DB::table('invoices')->sum(DB::raw('total_cents - paid_cents')) : null;
        $orders = DB::table('work_orders as w')->join('vehicles as v', 'v.id', '=', 'w.vehicle_id')->join('customers as c', 'c.id', '=', 'v.customer_id')->leftJoin('users as u', 'u.id', '=', 'w.technician_id')->select('w.*', 'v.plate', 'v.make', 'v.model', 'c.name as customer_name', 'u.name as technician_name')->whereNotIn('w.status', ['invoiced', 'cancelled'])->when(auth()->user()->role === 'technician', fn ($q) => $q->where('w.technician_id', auth()->id()))->orderByRaw("CASE w.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->orderBy('w.due_date')->limit(6)->get();
        $appointments = Access::allows(auth()->user(), 'appointments.read') ? DB::table('appointments as a')->join('vehicles as v', 'v.id', '=', 'a.vehicle_id')->join('customers as c', 'c.id', '=', 'v.customer_id')->select('a.*', 'v.plate', 'c.name as customer_name')->whereDate('a.starts_at', today())->whereNotIn('a.status', ['cancelled', 'no_show'])->orderBy('a.starts_at')->limit(5)->get() : collect();
        $lowStock = DB::table('parts')->whereColumn('stock', '<=', 'reorder_level')->orderBy('stock')->limit(4)->get();
        $activity = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->select('audit_logs.*', 'users.name')->when(! Access::allows(auth()->user(), 'audit'), fn ($q) => $q->where('user_id', auth()->id()))->orderByDesc('audit_logs.id')->limit(5)->get();

        return view('dashboard',compact('active','ready','estimates','revenue','outstanding','orders','appointments','lowStock','activity'));
    }
}
