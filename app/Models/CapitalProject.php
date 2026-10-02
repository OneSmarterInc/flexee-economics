<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

#[Fillable(['ulid', 'key', 'name', 'category', 'version', 'cash_flow_reference', 'risk_class', 'required_inputs', 'metadata', 'is_active'])]
class CapitalProject extends Model
{
    use HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'required_inputs' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function requiredInputs(): array
    {
        return $this->arrayAttribute('required_inputs');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function metadataSnapshot(): array
    {
        return $this->arrayAttribute('metadata');
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

        throw new InvalidArgumentException("Capital project attribute [{$key}] must be an array.");
    }
}
