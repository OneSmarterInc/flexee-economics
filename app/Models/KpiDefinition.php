<?php

namespace App\Models;

use App\Enums\KpiDefinitionStatus;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'key', 'name', 'description', 'weight', 'calculation_source', 'version', 'status', 'effective_from', 'effective_to', 'metadata'])]
class KpiDefinition extends Model
{
    use HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:6',
            'status' => KpiDefinitionStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }

    public function statusEnum(): KpiDefinitionStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof KpiDefinitionStatus) {
            return $status;
        }

        return KpiDefinitionStatus::from((string) $status);
    }

    /**
     * @return HasMany<KpiSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(KpiSnapshot::class);
    }
}
