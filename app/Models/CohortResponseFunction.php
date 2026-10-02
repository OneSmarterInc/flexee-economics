<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

#[Fillable(['ulid', 'key', 'name', 'description', 'version', 'source_week_number', 'target_week_number', 'input_definition', 'output_definition', 'bounds', 'parameters', 'is_active'])]
class CohortResponseFunction extends Model
{
    use HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'input_definition' => 'array',
            'output_definition' => 'array',
            'bounds' => 'array',
            'parameters' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function inputDefinition(): array
    {
        return $this->arrayAttribute('input_definition');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function outputDefinition(): array
    {
        return $this->arrayAttribute('output_definition');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function boundsDefinition(): array
    {
        return $this->arrayAttribute('bounds');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function parameterDefinition(): array
    {
        return $this->arrayAttribute('parameters');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function arrayAttribute(string $key): array
    {
        $value = $this->getAttribute($key);

        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException("Cohort response function attribute [{$key}] must be an array.");
    }
}
