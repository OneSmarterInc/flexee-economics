<?php

namespace App\Domain\Content;

final readonly class ContentPackageValidationResult
{
    /**
     * @param  list<array<string, mixed>>  $artifacts
     * @param  list<string>  $errors
     */
    public function __construct(
        public bool $valid,
        public string $manifestHash,
        public array $artifacts,
        public array $errors,
    ) {}
}
