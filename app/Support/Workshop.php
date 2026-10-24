<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Workshop
{
    public static function audit(string $action, string $subject): void
    {
        DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }

    public static function money(int|float|null $cents): string
    {
        return number_format(($cents ?? 0) / 100, 2);
    }

    public static function totals(int $id): array
    {
        $subtotal = (int) DB::table('order_items')->where('work_order_id', $id)->sum(DB::raw('quantity * unit_cents'));
        $rate = DB::table('work_orders')->where('id', $id)->value('tax_basis_points');
        $tax = (int) round($subtotal * $rate / 10000);

        return [$subtotal, $tax, $subtotal + $tax];
    }
}
