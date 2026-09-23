<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_content_package_id', 'artifact_key', 'artifact_type', 'visibility', 'path_reference', 'checksum_algorithm', 'checksum', 'actual_checksum', 'version', 'is_missing', 'metadata'])]
class ContentArtifact extends Model
{
    use HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (ContentArtifact $artifact): void {
            if (! SimulationContentPackage::query()->whereKey($artifact->simulation_content_package_id)->exists()) {
                throw new InvalidArgumentException('Content artifact must belong to a simulation content package.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Content artifacts are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Content artifacts are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'is_missing' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SimulationContentPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SimulationContentPackage::class, 'simulation_content_package_id');
    }
}
