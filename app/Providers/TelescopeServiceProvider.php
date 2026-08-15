<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The installed laravel/telescope (5.22.x) has no Telescope::ignoreMigrations():
        // its vendor TelescopeServiceProvider never calls loadMigrationsFrom(), only
        // publishesMigrations() for `vendor:publish`. Nothing auto-runs Telescope's
        // migrations, so the only guard needed is deleting the file `telescope:install`
        // publishes into database/migrations/ — which this template does not ship.
        // Re-verify after any telescope upgrade with:
        //   grep -rn "loadMigrationsFrom\|ignoreMigrations" vendor/laravel/telescope/

        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal): bool {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    // No gate() override: this provider is only ever registered when
    // app()->environment('local') is true (see AppServiceProvider::register()),
    // so the base viewTelescope gate the parent's authorization() checks is
    // never reached — the environment gate is the actual access control.
}
