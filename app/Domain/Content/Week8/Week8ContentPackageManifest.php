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
            'status' => 'specification_ready_package_missing',
            'package_root' => self::PACKAGE_ROOT,
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
                'opec_compliance_history',
                'current_cut_characteristics',
                'price_propagation_reference',
                'segment_position',
                'hedge_book_balance_sheet',
                'worked_example_prior',
            ],
            'deferred_runtime_capabilities' => [
                'scenario_probability_estimation',
                'price_propagation_engine',
                'integrated_segment_impact',
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
                'artifact_key' => 'week8_opec_compliance_history',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/opec_compliance_history.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_current_cut_characteristics',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/current_cut_characteristics.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_price_propagation_reference',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/price_propagation_reference.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_segment_position',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/segment_position.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week8_hedge_book_balance_sheet',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/hedge_book_balance_sheet.csv',
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
            function (array $artifact) use ($pathOverrides): array {
                $pathReference = $pathOverrides[$artifact['artifact_key']] ?? $artifact['path_reference'];
                $hash = is_file(base_path($pathReference))
                    ? hash_file('sha256', base_path($pathReference))
                    : null;

                return [
                    ...$artifact,
                    'path_reference' => $pathReference,
                    'checksum' => is_string($hash) ? $hash : null,
                    'metadata' => [
                        'package_root' => self::PACKAGE_ROOT,
                        'source_status' => 'awaiting_authoritative_package',
                    ],
                ];
            },
            $artifacts,
        ));
    }
}
