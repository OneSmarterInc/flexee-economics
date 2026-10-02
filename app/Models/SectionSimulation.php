<?php

namespace App\Models;

use App\Enums\SectionSimulationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SectionSimulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_id', 'simulation_id', 'simulation_variant_id', 'simulation_version_id', 'created_by_user_id', 'name', 'status', 'starts_at', 'ends_at', 'metadata'])]
class SectionSimulation extends Model
{
    /** @use HasFactory<SectionSimulationFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SectionSimulation $sectionSimulation): void {
            $section = Section::query()->findOrFail($sectionSimulation->section_id);
            $version = SimulationVersion::query()->findOrFail($sectionSimulation->simulation_version_id);

            if ($section->tenant_id !== $sectionSimulation->tenant_id) {
                throw new InvalidArgumentException('Section simulation tenant must match its section.');
            }

            if ($version->simulation_id !== $sectionSimulation->simulation_id || $version->simulation_variant_id !== $sectionSimulation->simulation_variant_id) {
                throw new InvalidArgumentException('Section simulation must reference a compatible simulation version.');
            }

            if ($sectionSimulation->created_by_user_id !== null) {
                $creator = User::query()->findOrFail($sectionSimulation->created_by_user_id);

                if ($creator->tenant_id !== $sectionSimulation->tenant_id) {
                    throw new InvalidArgumentException('Section simulation creator must belong to the same tenant.');
                }
            }
        });

        static::updating(function (SectionSimulation $sectionSimulation): void {
            $identityFields = ['tenant_id', 'section_id', 'simulation_id', 'simulation_variant_id', 'simulation_version_id'];

            if ($sectionSimulation->isDirty($identityFields)) {
                throw new InvalidArgumentException('Section simulations cannot be retargeted after assignment.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
            'status' => SectionSimulationStatus::class,
        ];
    }

    public function statusValue(): string
    {
        $status = $this->getAttribute('status');

        if ($status instanceof SectionSimulationStatus) {
            return $status->value;
        }

        return (string) $status;
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Simulation, $this>
     */
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(Simulation::class);
    }

    /**
     * @return BelongsTo<SimulationVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(SimulationVariant::class, 'simulation_variant_id');
    }

    /**
     * @return BelongsTo<SimulationVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SimulationVersion::class, 'simulation_version_id');
    }

    /**
     * @return HasMany<SectionSimulationWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(SectionSimulationWeek::class);
    }

    /**
     * @return HasMany<TeamSimulation, $this>
     */
    public function teamSimulations(): HasMany
    {
        return $this->hasMany(TeamSimulation::class);
    }
}
