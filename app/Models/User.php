<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

// external_id is deliberately absent from Fillable: it is assigned by HasExternalId on
// creation and must never be settable from request input. id is Hidden because the bigint
// primary key is internal — external_id is the only identifier that leaves the application.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['id', 'password', 'remember_token', 'two_factor_recovery_codes', 'two_factor_secret'])]
final class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasExternalId, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
