<?php

namespace Tests\Feature\Content;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyOperationsDashboard;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class Week14ReadinessGateTest extends TestCase
{
    public function test_week14_is_excluded_from_authoritative_computational_packages(): void
    {
        $manifest = app(AuthoritativeContentPackageManifest::class);

        $this->assertContains(14, array_keys($manifest->excludedWeeks()));
        $this->assertSame(
            'Week 14 is a board-defense assessment week with no computational package by design.',
            $manifest->excludedWeeks()[14],
        );
        $this->assertNotContains(14, $manifest->registrableWeeks());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Week 14 is a board-defense assessment week with no computational package by design.');

        $manifest->packageRoot(14);
    }

    public function test_week14_has_no_computational_package_root(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('halden-week14-data-package'));
        $this->assertFileDoesNotExist(base_path('halden-week14-data-package/MANIFEST.md'));
        $this->assertFileDoesNotExist(base_path('halden-week14-data-package/fixtures/week14_golden.json'));
    }

    public function test_week14_readiness_documents_capture_deliverables_rubric_visibility_and_history(): void
    {
        $inventory = $this->doc('docs/WEEK14_SOURCE_INVENTORY.md');
        $plan = $this->doc('docs/WEEK14_IMPLEMENTATION_PLAN.md');
        $implementation = $this->doc('docs/BATCH31A_IMPLEMENTATION.md');

        foreach ([$inventory, $plan, $implementation] as $document) {
            $this->assertStringContainsString('READY WITH NON-BLOCKING QUESTIONS', $document);
            $this->assertStringContainsString('no computational package', strtolower($document));
            $this->assertStringContainsString('board-defense', strtolower($document));
        }

        $this->assertStringContainsString('Board presentation and live defense', $inventory);
        $this->assertStringContainsString('Final synthesis memo', $inventory);
        $this->assertStringContainsString('Strategic coherence', $inventory);
        $this->assertStringContainsString('Decision quality', $inventory);
        $this->assertStringContainsString('Self-understanding', $inventory);
        $this->assertStringContainsString('Students must not see peer submissions', $implementation);
        $this->assertStringContainsString('Week 14 consumes, rather than creates, economic history', $inventory);
        $this->assertStringContainsString('Do not implement an economic engine', $inventory);

        $this->assertStringContainsString('Week 14 assessment workflow', $plan);
        $this->assertStringContainsString('Missing evidence should be shown explicitly', $plan);
    }

    public function test_faculty_dashboard_treats_week14_as_assessment_not_missing_package_blocker(): void
    {
        $dashboard = new FacultyOperationsDashboard;
        $reflection = new ReflectionClass($dashboard);
        $method = $reflection->getMethod('timelineState');
        $method->setAccessible(true);

        $this->assertSame('assessment', $method->invoke(
            $dashboard,
            (new SectionSimulationWeek(['status' => SectionSimulationWeekStatus::Draft]))
                ->setRelation('definition', new SimulationWeek(['week_number' => 14])),
            null,
            'missing',
        ));
    }

    private function doc(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
