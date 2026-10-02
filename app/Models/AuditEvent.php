<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ulid', 'tenant_id', 'actor_user_id', 'action', 'auditable_type', 'auditable_id', 'before_state', 'after_state', 'metadata', 'occurred_at'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
