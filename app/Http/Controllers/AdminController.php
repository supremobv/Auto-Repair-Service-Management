<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Access;
use App\Support\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function settings()
    {
        Access::check('settings');

        return view('admin.settings');
    }

    public function saveSettings(Request $r)
    {
        Access::check('settings');
        $data = $r->validate(['name' => 'required|string|max:120', 'tagline' => 'nullable|string|max:150', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000', 'currency' => 'required|in:PHP,USD,SGD,EUR,GBP,AUD,CAD,MYR', 'tax_rate' => 'required|decimal:0,2|between:0,100', 'invoice_footer' => 'nullable|string|max:2000', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=3000,max_height=3000', 'remove_logo' => 'nullable|boolean']);
        $settings = DB::table('settings')->first();
        $old = $settings->logo ?? null;
        if ($settings && $data['currency'] !== $settings->currency && (DB::table('work_orders')->exists() || DB::table('parts')->exists() || DB::table('services')->exists())) {
            throw ValidationException::withMessages(['currency' => 'Currency is locked after financial records are created. Use a separate database for a business with a different currency.']);
        }
        $data['tax_basis_points'] = (int) round((float) $data['tax_rate'] * 100);
        unset($data['tax_rate'],$data['logo'],$data['remove_logo']);
        if ($r->boolean('remove_logo')) {
            $data['logo'] = null;
        }
        if ($r->hasFile('logo')) {
            $image = imagecreatefromstring(file_get_contents($r->file('logo')->getRealPath()));
            if (! $image) {
                throw ValidationException::withMessages(['logo' => 'This image could not be processed. Please upload a different image.']);
            }
            $data['logo'] = 'logos/'.Str::uuid().'.png';
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put($data['logo'], $bytes);
        }
        DB::transaction(function () use ($data, $settings) {
            if ($settings) {
                DB::table('settings')->where('id', $settings->id)->update([...$data, 'updated_at' => now()]);
            } else {
                DB::table('settings')->insert([...$data, 'created_at' => now(), 'updated_at' => now()]);
            }
            Workshop::audit('Updated business settings', 'Configuration');
        });

        // Keep prior logos because issued invoice snapshots can still reference them.
        return back()->with('success', 'Business settings updated. New invoices will use these details.');
    }

    public function users()
    {
        Access::check('users');

        return view('admin.users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function saveUser(Request $r, ?int $id = null)
    {
        Access::check('users');
        $user = $id ? User::findOrFail($id) : null;
        $data = $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(Access::ROLES)], 'active' => 'required|boolean', 'password' => [$id ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($data, $id, $user) {
            $admins = User::where('role', 'admin')->where('active', true)->lockForUpdate()->get();
            if ($id === auth()->id() && (! $data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'You cannot disable or demote your own administrator account.']);
            }
            if ($user?->role === 'admin' && $user->active && (! $data['active'] || $data['role'] !== 'admin') && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active administrator is required.']);
            }
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['must_change_password'] = true;
            }
            if ($user) {
                $user->update($data);
                if (isset($data['password']) || ! $data['active'] || $user->wasChanged('role')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            } else {
                $user = User::create($data);
            }
            Workshop::audit('Saved staff account', '#'.$user->id);
        });

        return back()->with('success', 'Staff account saved. New and reset passwords must be changed at next login.');
    }

    public function audit()
    {
        Access::check('audit');

        return view('admin.audit', ['logs' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->select('audit_logs.*', 'users.name')->orderByDesc('audit_logs.id')->paginate(30)]);
    }

    public function reports(Request $r)
    {
        Access::check('reports');
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $r->input('from', today()->startOfMonth()->toDateString());
        $to = $r->input('to', today()->toDateString());
        $payments = DB::table('payments')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $collected = (clone $payments)->sum('amount_cents');
        $byMethod = (clone $payments)->select('method', DB::raw('SUM(amount_cents) as total'))->groupBy('method')->get();
        $invoiced = DB::table('invoices')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('total_cents');
        $receivables = DB::table('invoices')->sum(DB::raw('total_cents-paid_cents'));
        $overdue = DB::table('invoices')->where('due_date', '<', today())->sum(DB::raw('total_cents-paid_cents'));
        $stockValue = DB::table('parts')->sum(DB::raw('stock*cost_cents'));
        $jobs = DB::table('work_orders')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get();

        return view('admin.reports', compact('from', 'to', 'collected', 'invoiced', 'receivables', 'overdue', 'stockValue', 'byMethod', 'jobs'));
    }

    public function export(Request $r)
    {
        Access::check('reports');
        $data = $r->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        Workshop::audit('Exported payments', $data['from'].' to '.$data['to']);

        return response()->streamDownload(function () use ($data) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Invoice', 'Date', 'Amount', 'Method', 'Reference']);
            DB::table('payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')->whereBetween('p.created_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])->select('p.*', 'i.number')->orderBy('p.id')->chunk(500, function ($rows) use ($stream) {
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, [$row->number, $row->created_at, Workshop::money($row->amount_cents), $row->method, $row->reference]));
                }
            });
            fclose($stream);
        }, 'payments.csv', ['Content-Type' => 'text/csv']);
    }

    public function movements()
    {
        Access::check('inventory.read');

        return view('admin.movements', ['movements' => DB::table('stock_movements as m')->join('parts as p','p.id','=','m.part_id')->leftJoin('users as u','u.id','=','m.user_id')->select('m.*','p.name','p.sku','u.name as user_name')->orderByDesc('m.id')->paginate(25)]);
    }
}
