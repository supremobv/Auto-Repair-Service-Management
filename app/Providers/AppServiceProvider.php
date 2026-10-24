<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            // A container-scoped value avoids a query for every Blade partial.
            if (! app()->bound('workshop.business')) {
                app()->instance('workshop.business', DB::table('settings')->first());
            }
            $view->with('shop', app('workshop.business'));
        });
        Paginator::defaultView('partials.pagination');
    }
}
