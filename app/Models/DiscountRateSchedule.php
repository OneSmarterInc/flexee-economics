<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

#[Fillable(['ulid', 'key', 'name', 'description', 'version', 'source_week_number', 'target_week_number', 'classification_rules', 'classification_outcomes', 'is_active'])]
class DiscountRateSchedule extends Model
{
    use HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'classification_rules' => 'array',
            'classification_outcomes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    public function classificationRules(): array
    {
        $rules = $this->arrayAttribute('classification_rules');

        return array_values(array_filter($rules, fn (mixed $rule): bool => is_array($rule)));
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws JsonException
     */
    public function classificationOutcomes(): array
    {
        $outcomes = $this->arrayAttribute('classification_outcomes');
        $normalized = [];

        foreach ($outcomes as $key => $outcome) {
            if (is_array($outcome)) {
                $normalized[(string) $key] = $outcome;
            }
        }

        return $normalized;
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

        throw new InvalidArgumentException("Discount rate schedule attribute [{$key}] must be an array.");
    }
}
