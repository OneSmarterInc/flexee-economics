<?php

namespace App\Domain\Content\Week6;

final class Week6ContentPackageManifest
{
    public const PACKAGE_TYPE = 'week6_capital_allocation';

    public const PACKAGE_ROOT = 'halden-week6-data-package';

    /**
     * @return array<string, mixed>
     */
    public function manifest(string $version): array
    {
        return [
            'package_type' => self::PACKAGE_TYPE,
            'version' => $version,
            'week_number' => 6,
            'title' => 'Week 6 Capital Allocation',
            'status' => 'authoritative_package_available',
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
            'deferred_runtime_capabilities' => [
                'npv_calculation',
                'irr_calculation',
                'capital_envelope_feasibility',
                'week6_what_if',
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
                'artifact_key' => 'week6_student_workbook',
                'artifact_type' => 'workbook',
                'visibility' => 'student',
                'path_reference' => self::PACKAGE_ROOT.'/halden_week6.xlsx',
                'version' => 'student-v1',
            ],
            [
                'artifact_key' => 'week6_student_notebook',
                'artifact_type' => 'notebook',
                'visibility' => 'student',
                'path_reference' => self::PACKAGE_ROOT.'/halden_week6_analysis.ipynb',
                'version' => 'student-v1',
            ],
            [
                'artifact_key' => 'week6_manifest',
                'artifact_type' => 'manifest',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/MANIFEST.md',
                'version' => 'manifest-v1',
            ],
            [
                'artifact_key' => 'week6_project_cashflows',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/project_cashflows.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_cohort_discount_schedule',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/cohort_discount_schedule.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_cost_of_capital',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/cost_of_capital.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_currency_helix',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/currency_helix.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_forecast_haircuts',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/forecast_haircuts.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_worked_example_prior',
                'artifact_type' => 'dataset',
                'visibility' => 'shared',
                'path_reference' => self::PACKAGE_ROOT.'/data/worked_example_prior.csv',
                'version' => 'data-v1',
            ],
            [
                'artifact_key' => 'week6_faculty_solution_workbook',
                'artifact_type' => 'solution_workbook',
                'visibility' => 'solution',
                'path_reference' => self::PACKAGE_ROOT.'/faculty/halden_week6_FACULTY_SOLUTION.xlsx',
                'version' => 'faculty-v1',
            ],
            [
                'artifact_key' => 'week6_expected_outputs',
                'artifact_type' => 'expected_outputs',
                'visibility' => 'solution',
                'path_reference' => self::PACKAGE_ROOT.'/fixtures/week6_golden.json',
                'version' => 'expected-v1',
            ],
            [
                'artifact_key' => 'week6_provenance',
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
                    ],
                ];
            },
            $artifacts,
        ));
    }
}
