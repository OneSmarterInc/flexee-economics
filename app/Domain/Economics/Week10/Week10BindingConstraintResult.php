<?php

namespace App\Domain\Economics\Week10;

final readonly class Week10BindingConstraintResult
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $binding,
        public string $sourceDependencyKey,
        public string $sourceValue,
        public string $rule,
    ) {}

    /**
     * @return array<string, string|bool>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'binding' => $this->binding,
            'source_dependency_key' => $this->sourceDependencyKey,
            'source_value' => $this->sourceValue,
            'rule' => $this->rule,
        ];
    }
}
