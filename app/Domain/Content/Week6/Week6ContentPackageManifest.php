<?php

namespace App\Domain\Content\Week6;

final class Week6ContentPackageManifest
{
    public const PACKAGE_TYPE = 'week6_capital_allocation';

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
            'status' => 'awaiting_authoritative_package',
            'required_sections' => [
                'student' => [
                    'workbook',
                    'notebook',
                    'instructions',
                ],
                'faculty' => [
                    'solution_workbook',
                    'solution_notebook',
                    'teaching_notes',
                ],
                'expected' => [
                    'outputs',
                    'validation_fixtures',
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
        return array_map(
            fn (array $artifact): array => [
                ...$artifact,
                'path_reference' => $pathOverrides[$artifact['artifact_key']] ?? $artifact['path_reference'],
            ],
            [
                [
                    'artifact_key' => 'week6_student_workbook',
                    'artifact_type' => 'workbook',
                    'visibility' => 'student',
                    'path_reference' => 'simulation-content/halden/week6/student/halden_week6.xlsx',
                    'version' => 'student-v1',
                ],
                [
                    'artifact_key' => 'week6_student_notebook',
                    'artifact_type' => 'notebook',
                    'visibility' => 'student',
                    'path_reference' => 'simulation-content/halden/week6/student/halden_week6_analysis.ipynb',
                    'version' => 'student-v1',
                ],
                [
                    'artifact_key' => 'week6_student_instructions',
                    'artifact_type' => 'instructions',
                    'visibility' => 'student',
                    'path_reference' => 'simulation-content/halden/week6/student/instructions.md',
                    'version' => 'student-v1',
                ],
                [
                    'artifact_key' => 'week6_faculty_solution_workbook',
                    'artifact_type' => 'solution_workbook',
                    'visibility' => 'solution',
                    'path_reference' => 'simulation-content/halden/week6/faculty/halden_week6_solution.xlsx',
                    'version' => 'faculty-v1',
                ],
                [
                    'artifact_key' => 'week6_faculty_solution_notebook',
                    'artifact_type' => 'solution_notebook',
                    'visibility' => 'solution',
                    'path_reference' => 'simulation-content/halden/week6/faculty/halden_week6_solution.ipynb',
                    'version' => 'faculty-v1',
                ],
                [
                    'artifact_key' => 'week6_faculty_teaching_notes',
                    'artifact_type' => 'teaching_notes',
                    'visibility' => 'faculty',
                    'path_reference' => 'simulation-content/halden/week6/faculty/teaching_notes.md',
                    'version' => 'faculty-v1',
                ],
                [
                    'artifact_key' => 'week6_expected_outputs',
                    'artifact_type' => 'expected_outputs',
                    'visibility' => 'faculty',
                    'path_reference' => 'simulation-content/halden/week6/expected/outputs.json',
                    'version' => 'expected-v1',
                ],
                [
                    'artifact_key' => 'week6_validation_fixtures',
                    'artifact_type' => 'validation_fixtures',
                    'visibility' => 'faculty',
                    'path_reference' => 'simulation-content/halden/week6/expected/validation_fixtures.json',
                    'version' => 'expected-v1',
                ],
            ],
        );
    }
}
