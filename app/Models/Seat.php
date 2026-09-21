<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ulid', 'code', 'name', 'sort_order', 'content_ref', 'is_active'])]
class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
