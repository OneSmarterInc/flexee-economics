<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUlidRouteKey
{
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected static function bootHasUlidRouteKey(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }
}
