<?php

namespace App\Support;

use App\Models\User;

class Access
{
    public const ROLES = ['admin', 'manager', 'advisor', 'technician', 'cashier'];

    public static function allows(?User $user, string $ability): bool
    {
        if (! $user || ! $user->active) {
            return false;
        }
        if ($user->role === 'admin') {
            return true;
        }
        $permissions = [
            'manager' => ['dashboard', 'customers', 'vehicles', 'appointments', 'orders', 'orders.work', 'inventory', 'services', 'suppliers', 'billing', 'reports', 'audit'],
            'advisor' => ['dashboard', 'customers', 'vehicles', 'appointments', 'orders', 'orders.work', 'services.read', 'inventory.read', 'billing.read'],
            'technician' => ['dashboard', 'orders.read', 'orders.work', 'vehicles.read', 'services.read', 'inventory.read'],
            'cashier' => ['dashboard', 'customers.read', 'vehicles.read', 'orders.read', 'billing', 'reports'],
        ];
        $list = $permissions[$user->role] ?? [];

        return in_array($ability, $list) || (str_ends_with($ability, '.read') && in_array(substr($ability, 0, -5), $list));
    }

    public static function check(string $ability): void
    {
        abort_unless(self::allows(auth()->user(), $ability), 403);
    }
}
