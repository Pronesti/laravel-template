<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasExternalId
{
    public static function bootHasExternalId(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('external_id'))) {
                $model->setAttribute('external_id', (string) Str::uuid7());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
