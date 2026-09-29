<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        // NEW 29 Aug 2026 — centrex/laravel-accounting (Task #312) checks
        // this super-gate before every accounting.* ability; without it
        // the package denies everyone, even platform Admins. Same check
        // GLADE already uses everywhere else (role === 'ADMIN').
        Gate::define('accounting-admin', static fn ($agent): bool => $agent->role === 'ADMIN');
        // NEW 14 Aug 2026 — per Chris: vendor verification emails kept
        // linking to "localhost" no matter what APP_URL was set to in
        // .env, because Laravel's route()/url() helpers default to
        // building links from whatever host the CURRENT request came in
        // on (e.g. Admin browsing via http://localhost/... on his PC),
        // not from .env's APP_URL. Forcing the root URL here makes every
        // generated link (email links included) always use APP_URL,
        // regardless of which address Admin happens to be browsing from
        // — so changing APP_URL in .env is now the ONE place that
        // controls it, as expected.
        URL::forceRootUrl(config('app.url'));
        // FIXED 2 Aug 2026 — per Chris: Laravel's built-in pagination view
        // assumes Tailwind CSS is loaded; this app never loads Tailwind, so
        // the default view's "hidden"/"sm:flex" classes did nothing and BOTH
        // its mobile and desktop blocks rendered at once, including two
        // completely unstyled SVG arrow icons at raw browser-default size
        // (huge, solid black) — looked like a giant black shape swallowing
        // table rows on any screen where a list spanned more than one page.
        // This registers our own plain-inline-style view as the default for
        // every ->links() call in the app, fixing it everywhere at once.
        Paginator::defaultView('vendor.pagination.custom');
        Paginator::defaultSimpleView('vendor.pagination.custom');
    }
}
