<?php

namespace App\Models;

use App\Enums\DecisionFieldType;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\DecisionFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'decision_form_definition_id', 'field_key', 'label', 'field_type', 'is_required', 'display_order', 'help_text', 'unit', 'validation', 'options', 'visibility'])]
class DecisionFieldDefinition extends Model
{
    /** @use HasFactory<DecisionFieldDefinitionFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::updating(function (DecisionFieldDefinition $field): void {
            $materialFields = ['field_key', 'label', 'field_type', 'is_required', 'display_order', 'help_text', 'unit', 'validation', 'options', 'visibility'];
            $version = $field->formDefinition->simulationVersion;

            if ($field->isDirty($materialFields) && $version->isFrozen()) {
                throw new InvalidArgumentException('Decision fields for published or in-use versions cannot be materially modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'field_type' => DecisionFieldType::class,
            'is_required' => 'boolean',
            'validation' => 'array',
            'options' => 'array',
            'visibility' => 'array',
        ];
    }

    public function typeEnum(): DecisionFieldType
    {
        $type = $this->getAttribute('field_type');

        if ($type instanceof DecisionFieldType) {
            return $type;
        }

        return DecisionFieldType::from((string) $type);
    }

    /**
     * @return BelongsTo<DecisionFormDefinition, $this>
     */
    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(DecisionFormDefinition::class, 'decision_form_definition_id');
    }
}
