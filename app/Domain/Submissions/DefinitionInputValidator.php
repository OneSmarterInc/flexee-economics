<?php

namespace App\Domain\Submissions;

use App\Enums\DecisionFieldType;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\MemoDefinition;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DefinitionInputValidator
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function validateDecisionAnswers(DecisionFormDefinition $definition, array $answers, bool $final): array
    {
        $definition->loadMissing('fields');

        /** @var Collection<int, DecisionFieldDefinition> $fields */
        $fields = $definition->fields;
        $knownKeys = $fields->pluck('field_key')->all();
        $messages = [];

        foreach (array_keys($answers) as $key) {
            if (! in_array($key, $knownKeys, true)) {
                $messages["answers.{$key}"] = 'Unknown decision field.';
            }
        }

        $validated = [];

        foreach ($fields as $field) {
            $key = $field->field_key;
            $present = array_key_exists($key, $answers) && $answers[$key] !== null && $answers[$key] !== '';

            if ($final && $field->is_required && ! $present) {
                $messages["answers.{$key}"] = 'This field is required.';

                continue;
            }

            if (! $present) {
                continue;
            }

            try {
                $validated[$key] = $this->validateFieldValue($field, $answers[$key]);
            } catch (ValidationException $exception) {
                $messages["answers.{$key}"] = $exception->errors()['value'][0] ?? 'Invalid field value.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $validated;
    }

    public function validateMemoBody(MemoDefinition $definition, ?string $body, bool $final): string
    {
        $body = trim((string) $body);
        $messages = [];

        if ($final && $definition->is_required && $body === '') {
            $messages['body'] = 'Memo body is required.';
        }

        if ($definition->character_limit !== null && mb_strlen($body) > $definition->character_limit) {
            $messages['body'] = 'Memo exceeds the configured character limit.';
        }

        if ($definition->word_limit !== null && $this->wordCount($body) > $definition->word_limit) {
            $messages['body'] = 'Memo exceeds the configured word limit.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $body;
    }

    public function wordCount(string $body): int
    {
        $body = trim($body);

        if ($body === '') {
            return 0;
        }

        return count(preg_split('/\s+/', $body) ?: []);
    }

    private function validateFieldValue(DecisionFieldDefinition $field, mixed $value): mixed
    {
        $validation = $this->fieldValidation($field);

        return match ($field->typeEnum()) {
            DecisionFieldType::Integer => $this->validateInteger($value, $validation),
            DecisionFieldType::Decimal,
            DecisionFieldType::Percentage,
            DecisionFieldType::Currency => $this->validateNumber($value, $validation),
            DecisionFieldType::Select,
            DecisionFieldType::Radio => $this->validateOption($field, $value),
            DecisionFieldType::Boolean => $this->validateBoolean($value),
            DecisionFieldType::ShortText => $this->validateShortText($value, $validation),
        };
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function validateInteger(mixed $value, array $validation): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['value' => 'Value must be an integer.']);
        }

        $integer = (int) $value;
        $this->validateNumericBounds($integer, $validation);

        return $integer;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function validateNumber(mixed $value, array $validation): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['value' => 'Value must be numeric.']);
        }

        $number = (float) $value;
        $this->validateNumericBounds($number, $validation);

        return $number;
    }

    private function validateOption(DecisionFieldDefinition $field, mixed $value): string
    {
        $value = (string) $value;
        $allowed = [];

        foreach ($this->fieldOptions($field) as $option) {
            $allowed[] = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
        }

        $allowed = array_values(array_filter($allowed));

        if ($allowed === [] || ! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages(['value' => 'Value is not an allowed option.']);
        }

        return $value;
    }

    private function validateBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        throw ValidationException::withMessages(['value' => 'Value must be boolean.']);
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function validateShortText(mixed $value, array $validation): string
    {
        if (! is_scalar($value)) {
            throw ValidationException::withMessages(['value' => 'Value must be text.']);
        }

        $text = trim((string) $value);
        $maxLength = isset($validation['max_length']) ? (int) $validation['max_length'] : null;

        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            throw ValidationException::withMessages(['value' => 'Value exceeds maximum length.']);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function validateNumericBounds(int|float $value, array $validation): void
    {
        if (array_key_exists('min', $validation) && $value < (float) $validation['min']) {
            throw ValidationException::withMessages(['value' => 'Value is below the configured minimum.']);
        }

        if (array_key_exists('max', $validation) && $value > (float) $validation['max']) {
            throw ValidationException::withMessages(['value' => 'Value is above the configured maximum.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldValidation(DecisionFieldDefinition $field): array
    {
        $validation = $field->getAttribute('validation');

        return is_array($validation) ? $validation : [];
    }

    /**
     * @return list<mixed>
     */
    private function fieldOptions(DecisionFieldDefinition $field): array
    {
        $options = $field->getAttribute('options');

        return is_array($options) ? array_values($options) : [];
    }
}
