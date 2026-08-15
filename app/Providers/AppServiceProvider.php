<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\VerificationLink;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Telescope\TelescopeServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Model::shouldBeStrict();
        DB::prohibitDestructiveCommands($this->app->isProduction());
        JsonResource::withoutWrapping();

        // Explicitly the sanctum guard: throttle:api runs on the middleware group,
        // BEFORE the route-level auth:sanctum switches the default guard, so an
        // argless $request->user() would consult the session guard and always
        // return null for bearer requests — collapsing every token client behind
        // one NAT onto a single shared IP bucket.
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user('sanctum');
            $identifier = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;

            return Limit::perMinute(60)->by(is_scalar($identifier)
                ? 'user:'.$identifier
                : 'ip:'.$request->ip());
        });

        Gate::define('viewApiDocs', fn (?User $user): bool => $this->app->environment('local'));

        // The URL policy — relative signing, external_id, email hash, expiry, the
        // FRONTEND_URL prefix — lives in App\Support\VerificationLink. This closure
        // only points the notification at it.
        VerifyEmail::createUrlUsing(fn (User $notifiable): string => VerificationLink::mint($notifiable));

        ResetPassword::createUrlUsing(function (mixed $user, string $token): string {
            if (! $user instanceof User) {
                throw new InvalidArgumentException(sprintf('Expected %s instance.', User::class));
            }

            return sprintf(
                '%s/reset-password/%s?email=%s',
                rtrim(Config::string('app.frontend_url'), '/'),
                $token,
                urlencode($user->getEmailForPasswordReset()),
            );
        });
    }
}
