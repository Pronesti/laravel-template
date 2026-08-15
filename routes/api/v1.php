<?php

declare(strict_types=1);

// Registered from bootstrap/app.php under the /api/v1 prefix.
// Fortify registers its own routes under the same prefix via config/fortify.php.
//
// Fortify only keeps the UNAUTHENTICATED features (registration, password reset). Its
// authenticated routes are guarded with `auth:web`, which a bearer-token client can never
// satisfy, so email verification and the profile/password updates are registered here
// instead — same paths, correct guards, same App\Actions\Fortify actions. See AGENTS.md.

use App\Http\Controllers\Api\V1\IssueTokenController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RevokeTokenController;
use App\Http\Controllers\Api\V1\SendEmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\UpdatePasswordController;
use App\Http\Controllers\Api\V1\UpdateProfileInformationController;
use App\Http\Controllers\Api\V1\VerificationNoticeController;
use App\Http\Controllers\Api\V1\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Tighter than the 60/min api limiter: this endpoint takes credentials.
Route::post('tokens', IssueTokenController::class)->middleware('throttle:6,1');

// No auth:sanctum: the link arrives by email and is opened by a browser that holds no
// token. `signed:relative` matches how App\Support\VerificationLink signs the URL
// (relative, so the FRONTEND_URL host can be prepended); {user} binds on external_id
// via getRouteKeyName().
Route::get('email/verify/{user}/{hash}', VerifyEmailController::class)
    ->middleware(['signed:relative', 'throttle:6,1'])
    ->name('verification.verify');

// Laravel's `verified` middleware redirects non-JSON requests to this named route. Without
// it, any project adding ->middleware('verified') gets a RouteNotFoundException instead of a
// response. Disabling Fortify's emailVerification feature is what removed the original.
Route::get('email/verify', VerificationNoticeController::class)
    ->name('verification.notice');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', MeController::class);
    Route::delete('tokens/current', RevokeTokenController::class);

    Route::post('email/verification-notification', SendEmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::put('user/profile-information', UpdateProfileInformationController::class)
        ->name('user-profile-information.update');

    Route::put('user/password', UpdatePasswordController::class)
        ->name('user-password.update');
});
