<?php

namespace App\Domain\Content\Week8;

final class Week8ContentPackageManifest
{
    public const PACKAGE_TYPE = 'week8_opec_shock';

    public const PACKAGE_ROOT = 'halden-week8-data-package';

    /**
     * @return array<string, mixed>
     */
    public function manifest(string $version): array
    {
        return [
            'package_type' => self::PACKAGE_TYPE,
            'version' => $version,
            'week_number' => 8,
            'title' => 'Week 8 OPEC Shock',
            'status' => 'authoritative_package_available',
            'package_root' => self::PACKAGE_ROOT,
            'source_package' => [
                'manifest' => self::PACKAGE_ROOT.'/MANIFEST.md',
                'provenance' => self::PACKAGE_ROOT.'/fixtures/provenance.json',
                'golden_fixture' => self::PACKAGE_ROOT.'/fixtures/week8_golden.json',
            ],
            'required_sections' => [
                'student' => [
                    'workbook',
                    'notebook',
                    'canonical_datasets',
                ],
                'faculty' => [
                    'solution_workbook',
                ],
                'expected' => [
                    'outputs',
                    'provenance',
                ],
            ],
            'expected_datasets' => [
                'baseline_state',
                'compliance_history',
                'opec_scenarios',
                'propagation_coefficients',
                'worked_example_prior',
            ],
            'validated_scope' => [
                'package_inventory',
                'provenance_hashes',
                'csv_schema',
                'student_workbook_structure',
                'student_notebook_execution',
                'faculty_solution_structure',
                'golden_fixture_parity',
            ],
            'deferred_runtime_capabilities' => [
                'scenario_probability_estimation',
                'price_propagation_engine',
                'integrated_segment_impact',
                'week6_to_week8_cohort_response',
                'week8_what_if',
                'reasoning_versus_luck_faculty_view',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $pathOverrides
     * @return list<array<string, mixed>>
     */
    public function artifacts(array $pathOverrides = []): array
    {
        $provenance = $this->provenanceHashes();
        $artifacts = [
            [
                'artifact_key' => 'week8_student_workbook',
                'artifact_type' => 'workbook',
                'visibility' => 'student',
                'path_reference' => self::PACKAGE_ROOT.'/halden_week8.xlsx',
                'version' => 'student-v1',
            ],
            [
                'artifact_key' => 'week8_student_notebook',
                'artifact_type' => 'notebook',
                'visibility' => 'student',
                'path_reference' => self::PACKAGE_ROOT.'/halden_week8_analysis.ipynb',
                'version' => 'student-v1',
            ],
            [
                'artifact_key' => 'week8_manifest',
                'artifact_type' => 'manifest',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/MANIFEST.md',
                'version' => 'manifest-v1',
            ],
            [
                'artifact_key' => 'week8_baseline_state',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/baseline_state.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_compliance_history',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/compliance_history.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_opec_scenarios',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/opec_scenarios.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_propagation_coefficients',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/propagation_coefficients.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_worked_example_prior',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/worked_example_prior.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_faculty_solution_workbook',
                'artifact_type' => 'solution_workbook',
                'visibility' => 'solution',
                'path_reference' => self::PACKAGE_ROOT.'/faculty/halden_week8_FACULTY_SOLUTION.xlsx',
                'version' => 'faculty-v1',
            ],
            [
                'artifact_key' => 'week8_expected_outputs',
                'artifact_type' => 'expected_outputs',
                'visibility' => 'solution',
                'path_reference' => self::PACKAGE_ROOT.'/fixtures/week8_golden.json',
                'version' => 'expected-v1',
            ],
            [
                'artifact_key' => 'week8_provenance',
                'artifact_type' => 'provenance',
                'visibility' => 'solution',
                'path_reference' => self::PACKAGE_ROOT.'/fixtures/provenance.json',
                'version' => 'expected-v1',
            ],
        ];

        return array_values(array_map(
            function (array $artifact) use ($pathOverrides, $provenance): array {
                $pathReference = $pathOverrides[$artifact['artifact_key']] ?? $artifact['path_reference'];
                $relativePackagePath = str_starts_with($pathReference, self::PACKAGE_ROOT.'/')
                    ? substr($pathReference, strlen(self::PACKAGE_ROOT) + 1)
                    : $pathReference;
                $hash = $provenance[$relativePackagePath]
                    ?? (is_file(base_path($pathReference)) ? hash_file('sha256', base_path($pathReference)) : null);

                return [
                    ...$artifact,
                    'path_reference' => $pathReference,
                    'checksum' => is_string($hash) ? $hash : null,
                    'metadata' => [
                        'package_root' => self::PACKAGE_ROOT,
                        'source_status' => 'authoritative_package_available',
                        'provenance_path' => self::PACKAGE_ROOT.'/fixtures/provenance.json',
                    ],
                ];
            },
            $artifacts,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function provenanceHashes(): array
    {
        $path = base_path(self::PACKAGE_ROOT.'/fixtures/provenance.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! is_array($decoded['artifacts'] ?? null)) {
            return [];
        }

        /** @var array<string, string> $artifacts */
        $artifacts = $decoded['artifacts'];

        return $artifacts;
    }
}
