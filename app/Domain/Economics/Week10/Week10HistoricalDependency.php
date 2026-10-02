<?php

namespace App\Domain\Economics\Week10;

final readonly class Week10HistoricalDependency
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNRESOLVED = 'unresolved';

    public function __construct(
        public string $key,
        public string $status,
        public string $sourceWeek,
        public string $sourceEntity,
        public ?string $sourceId,
        public ?string $sourceVersion,
        public ?string $sourceValue,
        public ?string $unresolvedReason = null,
    ) {}

    public static function available(
        string $key,
        string $sourceWeek,
        string $sourceEntity,
        string $sourceValue,
        ?string $sourceId = null,
        ?string $sourceVersion = null,
    ): self {
        return new self(
            key: $key,
            status: self::STATUS_AVAILABLE,
            sourceWeek: $sourceWeek,
            sourceEntity: $sourceEntity,
            sourceId: $sourceId,
            sourceVersion: $sourceVersion,
            sourceValue: $sourceValue,
        );
    }

    public static function unresolved(
        string $key,
        string $sourceWeek,
        string $sourceEntity,
        string $reason,
    ): self {
        return new self(
            key: $key,
            status: self::STATUS_UNRESOLVED,
            sourceWeek: $sourceWeek,
            sourceEntity: $sourceEntity,
            sourceId: null,
            sourceVersion: null,
            sourceValue: null,
            unresolvedReason: $reason,
        );
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status,
            'source_week' => $this->sourceWeek,
            'source_entity' => $this->sourceEntity,
            'source_id' => $this->sourceId,
            'source_version' => $this->sourceVersion,
            'source_value' => $this->sourceValue,
            'unresolved_reason' => $this->unresolvedReason,
        ];
    }
}
