<?php

namespace App\Domain\Submissions;

use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;

final class DecisionDefinitionSnapshotter
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(DecisionFormDefinition $definition): array
    {
        $definition->loadMissing('fields');

        return [
            'definition' => [
                'id' => $definition->id,
                'ulid' => $definition->ulid,
                'simulation_version_id' => $definition->simulation_version_id,
                'simulation_week_id' => $definition->simulation_week_id,
                'key' => $definition->key,
                'name' => $definition->name,
                'version' => $definition->version,
                'is_required' => (bool) $definition->is_required,
                'status' => $definition->status,
                'metadata' => $definition->metadata ?? [],
            ],
            'fields' => $definition->fields
                ->sortBy([['display_order', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn (DecisionFieldDefinition $field): array => [
                    'id' => $field->id,
                    'ulid' => $field->ulid,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'field_type' => $field->typeEnum()->value,
                    'is_required' => (bool) $field->is_required,
                    'display_order' => $field->display_order,
                    'help_text' => $field->help_text,
                    'unit' => $field->unit,
                    'validation' => $field->validation ?? [],
                    'options' => $field->options ?? [],
                    'visibility' => $field->visibility ?? [],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function snapshotForSubmission(DecisionFormDefinition $definition, array $answers): array
    {
        $snapshot = $this->snapshot($definition);
        $snapshot['submitted_answers'] = $answers;
        $snapshot['available_alternatives'] = $this->alternativesFromSnapshot($snapshot, $answers);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function alternativesFromSnapshot(array $snapshot, array $answers): array
    {
        $alternatives = [];

        foreach (($snapshot['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['field_key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $options = $this->normalizeOptions($field['options'] ?? []);
            $selected = $answers[$fieldKey] ?? null;

            $alternatives[$fieldKey] = [
                'field_key' => $fieldKey,
                'label' => $field['label'] ?? $fieldKey,
                'field_type' => $field['field_type'] ?? null,
                'unit' => $field['unit'] ?? null,
                'validation' => $field['validation'] ?? [],
                'selected' => $selected,
                'options' => $options,
                'not_selected_options' => array_values(array_filter(
                    $options,
                    fn (array $option): bool => $option['value'] !== (string) $selected,
                )),
            ];
        }

        return $alternatives;
    }

    /**
     * @param  list<mixed>|array<string, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $option) {
            if (is_array($option)) {
                $value = (string) ($option['value'] ?? '');
                $label = (string) ($option['label'] ?? $value);
            } else {
                $value = (string) $option;
                $label = $value;
            }

            if ($value === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $normalized;
    }
}
