<?php

declare(strict_types=1);

use Illuminate\Session\Middleware\StartSession;
use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    */

    'home' => '/',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    */

    'prefix' => 'api/v1',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['api', StartSession::class],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    */

    'limiters' => [
        // Only 'login' has a matching RateLimiter::for() registration. The 'two-factor' and
        // 'passkeys' entries Fortify ships by default are gone: both features are disabled,
        // so the keys named limiters that were never defined.
        'login' => 'login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    'views' => false,

    // Passkeys are not configured: the "passkeys" feature is not in the list below,
    // so Fortify never registers passkey routes and this template never publishes
    // the passkeys table migration.

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        // Only the UNAUTHENTICATED Fortify features stay on. Fortify protects its
        // authenticated routes with `auth:` . $this->guard, and 'guard' above must stay
        // 'web' — Fortify resolves a StatefulGuard and calls $guard->login(), which
        // Sanctum's RequestGuard does not implement, so 'sanctum' fatals. The consequence
        // is that any Fortify-registered authenticated route answers 401 to a bearer
        // token, since a token client carries no session cookie.
        //
        // emailVerification / updateProfileInformation / updatePasswords are therefore
        // disabled here and hand-registered in routes/api/v1.php behind auth:sanctum
        // (or a signed URL), reusing the published App\Actions\Fortify actions so no
        // validation logic is duplicated.
        Features::registration(),
        Features::resetPasswords(),

        // Features::emailVerification(),        -> routes/api/v1.php
        // Features::updateProfileInformation(), -> routes/api/v1.php
        // Features::updatePasswords(),          -> routes/api/v1.php

        // Two-factor is deliberately disabled: the template ships no 2FA token-challenge
        // endpoint, so enabling this would lock token clients out. See AGENTS.md.
        // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],

];
