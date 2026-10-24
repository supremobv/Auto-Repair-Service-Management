<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Resources
{
    // Every table, column and validation rule is defined here, never taken from user input.
    public static function all(): array
    {
        return [
            'customers' => ['title' => 'Customers', 'singular' => 'customer', 'ability' => 'customers', 'search' => ['name', 'email', 'phone'], 'fields' => [
                'name' => ['Name', 'text', 'required|string|max:150'], 'email' => ['Email', 'email', 'nullable|email|max:255'], 'phone' => ['Phone', 'text', 'required|string|max:40'], 'address' => ['Address', 'textarea', 'nullable|string|max:1000'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:3000'],
            ], 'columns' => ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email']],
            'vehicles' => ['title' => 'Vehicles', 'singular' => 'vehicle', 'ability' => 'vehicles', 'search' => ['plate', 'make', 'model', 'vin'], 'fields' => [
                'customer_id' => ['Owner', 'customers', 'required|exists:customers,id'], 'plate' => ['License plate', 'text', 'required|string|max:30'], 'make' => ['Make', 'text', 'required|string|max:80'], 'model' => ['Model', 'text', 'required|string|max:80'], 'year' => ['Year', 'number', 'required|integer|min:1900|max:2100'], 'vin' => ['VIN / chassis number', 'text', 'nullable|string|max:30'], 'mileage' => ['Odometer (km)', 'number', 'required|integer|min:0|max:10000000'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:3000'],
            ], 'columns' => ['plate' => 'Plate', 'make' => 'Make', 'model' => 'Model', 'year' => 'Year', 'mileage' => 'Odometer']],
            'appointments' => ['title' => 'Appointments', 'singular' => 'appointment', 'ability' => 'appointments', 'search' => ['service', 'status'], 'fields' => [
                'vehicle_id' => ['Vehicle', 'vehicles', 'required|exists:vehicles,id'], 'technician_id' => ['Technician', 'technicians', 'nullable|exists:users,id'], 'service' => ['Requested service', 'text', 'required|string|max:200'], 'starts_at' => ['Start', 'datetime-local', 'required|date'], 'ends_at' => ['End', 'datetime-local', 'required|date|after:starts_at'], 'status' => ['Status', ['scheduled' => 'Scheduled', 'checked_in' => 'Checked in', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'required|in:scheduled,checked_in,completed,cancelled,no_show'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:3000'],
            ], 'columns' => ['service' => 'Service', 'vehicle_label' => 'Vehicle', 'starts_at' => 'Start', 'technician_name' => 'Technician', 'status' => 'Status']],
            'inventory' => ['table' => 'parts', 'title' => 'Parts & inventory', 'singular' => 'part', 'ability' => 'inventory', 'search' => ['sku', 'name'], 'fields' => [
                'sku' => ['SKU', 'text', 'required|string|max:60'], 'name' => ['Part name', 'text', 'required|string|max:150'], 'supplier_id' => ['Supplier', 'suppliers', 'nullable|exists:suppliers,id'], 'cost' => ['Unit cost', 'number', 'required|decimal:0,2|min:0|max:1000000'], 'price' => ['Selling price', 'number', 'required|decimal:0,2|min:0|max:1000000'], 'reorder_level' => ['Low stock threshold', 'number', 'required|integer|min:0|max:1000000'],
            ], 'columns' => ['sku' => 'SKU', 'name' => 'Part', 'stock' => 'On hand', 'reorder_level' => 'Reorder at', 'price_cents' => 'Selling price']],
            'services' => ['title' => 'Service catalog', 'singular' => 'service', 'ability' => 'services', 'search' => ['name', 'description'], 'fields' => [
                'name' => ['Service name', 'text', 'required|string|max:150'], 'description' => ['Description', 'textarea', 'nullable|string|max:2000'], 'price' => ['Labor price', 'number', 'required|decimal:0,2|min:0|max:1000000'], 'duration_minutes' => ['Duration (minutes)', 'number', 'required|integer|min:5|max:1440'],
            ], 'columns' => ['name' => 'Service', 'duration_minutes' => 'Minutes', 'price_cents' => 'Labor price']],
            'suppliers' => ['title' => 'Suppliers', 'singular' => 'supplier', 'ability' => 'suppliers', 'search' => ['name', 'email', 'phone'], 'fields' => [
                'name' => ['Supplier name', 'text', 'required|string|max:150'], 'email' => ['Email', 'email', 'nullable|email|max:255'], 'phone' => ['Phone', 'text', 'nullable|string|max:40'], 'address' => ['Address', 'textarea', 'nullable|string|max:2000'],
            ], 'columns' => ['name' => 'Supplier', 'email' => 'Email', 'phone' => 'Phone']],
        ];
    }

    public static function get(string $key): array
    {
        abort_unless(isset(self::all()[$key]), 404);

        return self::all()[$key];
    }

    public static function options(): array
    {
        return [
            'customers' => DB::table('customers')->orderBy('name')->pluck('name', 'id'),
            'vehicles' => DB::table('vehicles')->orderBy('plate')->get()->mapWithKeys(fn ($v) => [$v->id => "$v->plate · $v->make $v->model"]),
            'technicians' => User::where('active', true)->where('role', 'technician')->orderBy('name')->pluck('name', 'id'),
            'suppliers' => DB::table('suppliers')->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
