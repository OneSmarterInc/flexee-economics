<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\GeneratedArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'artifact_key', 'artifact_type', 'version', 'hash', 'storage_disk', 'storage_path', 'metadata'])]
class GeneratedArtifact extends Model
{
    /** @use HasFactory<GeneratedArtifactFactory> */
    use HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
