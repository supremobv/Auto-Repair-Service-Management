<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ResourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::view('/password', 'auth.password');
    Route::post('/password', [AuthController::class, 'password'])->middleware('throttle:10,1');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/create', [OrderController::class, 'create']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::put('/orders/{id}', [OrderController::class, 'update'])->whereNumber('id');
    Route::put('/orders/{id}/assignment', [OrderController::class, 'assign'])->whereNumber('id');
    Route::post('/orders/{id}/items', [OrderController::class, 'item'])->whereNumber('id');
    Route::delete('/orders/{id}/items/{item}', [OrderController::class, 'removeItem'])->whereNumber(['id', 'item']);
    Route::post('/orders/{id}/status', [OrderController::class, 'transition'])->whereNumber('id');
    Route::post('/orders/{id}/invoice', [OrderController::class, 'invoice'])->whereNumber('id');
    Route::get('/invoices', [BillingController::class, 'index']);
    Route::get('/invoices/{id}', [BillingController::class, 'show'])->whereNumber('id');
    Route::post('/invoices/{id}/payments', [BillingController::class, 'payment'])->middleware('throttle:30,1')->whereNumber('id');
    Route::get('/vehicles/{id}/history', [ResourceController::class, 'history'])->whereNumber('id');
    Route::get('/stock-movements', [AdminController::class, 'movements']);
    Route::post('/inventory/{id}/stock', [ResourceController::class, 'stock'])->whereNumber('id');
    Route::get('/manage/{resource}', [ResourceController::class, 'index']);
    Route::get('/manage/{resource}/create', [ResourceController::class, 'form']);
    Route::get('/manage/{resource}/{id}/edit', [ResourceController::class, 'form'])->whereNumber('id');
    Route::post('/manage/{resource}', [ResourceController::class, 'save']);
    Route::put('/manage/{resource}/{id}', [ResourceController::class, 'save'])->whereNumber('id');
    Route::delete('/manage/{resource}/{id}', [ResourceController::class, 'delete'])->whereNumber('id');
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::post('/settings', [AdminController::class, 'saveSettings']);
    Route::get('/staff', [AdminController::class, 'users']);
    Route::post('/staff', [AdminController::class, 'saveUser']);
    Route::put('/staff/{id}', [AdminController::class, 'saveUser'])->whereNumber('id');
    Route::get('/audit', [AdminController::class, 'audit']);
    Route::get('/reports', [AdminController::class, 'reports']);
    Route::get('/reports/export',[AdminController::class, 'export']);
    Route::view('/help','help');
});
