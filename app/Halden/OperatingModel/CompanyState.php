<?php

namespace App\Halden\OperatingModel;

/**
 * What carries from one quarter to the next for one team's company.
 */
final class CompanyState
{
    /**
     * @param  array<string, int>  $heldUp  consecutive quarters each station price has been held above its going rate
     * @param  array<string, int>  $heldDown  consecutive quarters each station price has been held below its going rate
     * @param  array<string, float>  $hedges  hedges opened last quarter, settled this quarter
     * @param  array<string, int>  $projects  committed project key => quarters since commitment
     */
    public function __construct(
        public float $permianProd,
        public int $prevRigs,
        public string $rotStatus,          // running | idle | closed
        public float $capitalEmployed,
        public float $netDebt,
        public float $assetHealth,
        public float $europeVolumeFactor = 1.0,
        public array $heldUp = [],
        public array $heldDown = [],
        public array $hedges = [],
        public array $projects = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, mixed>  $a */
    public static function fromArray(array $a): self
    {
        return new self(
            permianProd: (float) $a['permianProd'],
            prevRigs: (int) $a['prevRigs'],
            rotStatus: (string) $a['rotStatus'],
            capitalEmployed: (float) $a['capitalEmployed'],
            netDebt: (float) $a['netDebt'],
            assetHealth: (float) $a['assetHealth'],
            europeVolumeFactor: (float) ($a['europeVolumeFactor'] ?? 1.0),
            heldUp: (array) ($a['heldUp'] ?? []),
            heldDown: (array) ($a['heldDown'] ?? []),
            hedges: array_map('floatval', (array) ($a['hedges'] ?? [])),
            projects: array_map('intval', (array) ($a['projects'] ?? [])),
        );
    }
}
