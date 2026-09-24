<?php

namespace App\Domain\Demo;

use App\Domain\Content\SimulationContentResolver;
use App\Domain\Content\Week4\Week4ContentPackageRegistrationService;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Interpretation\InterpretiveAssistantService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Models\DecisionFormDefinition;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use App\Models\Tenant;
use App\Models\User;
use InvalidArgumentException;
use Throwable;

final readonly class DemoHealthCheckService
{
    public const DEMO_TENANT_SLUG = 'halden-university-demo';

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->check('demo_tenant', fn (): string => $this->tenant()->name),
            $this->check('demo_faculty', fn (): string => $this->faculty()->email),
            $this->check('demo_student', fn (): string => $this->student()->email),
            $this->check('section_simulation', fn (): string => $this->sectionSimulation()->name ?? 'Section A Demo Halden Energy'),
            $this->check('week4_runtime_open', fn (): string => $this->week4Runtime()->statusValue()),
            $this->check('week4_content_package', fn (): string => $this->contentPackage()->version),
            $this->check('week4_content_activation', fn (): string => $this->resolvedPackageVersion()),
            $this->check('week4_decision_definition', fn (): string => $this->decisionDefinition()->name),
            $this->check('week4_memo_definition', fn (): string => $this->memoDefinition()->title),
            $this->check('execution_services', fn (): string => $this->serviceBindings()),
        ];
    }

    public function healthy(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['status'] !== 'ok') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  callable(): string  $callback
     * @return array{name: string, status: string, detail: string}
     */
    private function check(string $name, callable $callback): array
    {
        try {
            return [
                'name' => $name,
                'status' => 'ok',
                'detail' => $callback(),
            ];
        } catch (Throwable $throwable) {
            return [
                'name' => $name,
                'status' => 'failed',
                'detail' => $throwable->getMessage(),
            ];
        }
    }

    private function tenant(): Tenant
    {
        return Tenant::query()
            ->where('slug', self::DEMO_TENANT_SLUG)
            ->firstOrFail();
    }

    private function faculty(): User
    {
        return User::query()
            ->where('tenant_id', $this->tenant()->id)
            ->where('email', 'faculty@example.test')
            ->firstOrFail();
    }

    private function student(): User
    {
        return User::query()
            ->where('tenant_id', $this->tenant()->id)
            ->where('email', 'student11@example.test')
            ->firstOrFail();
    }

    private function sectionSimulation(): SectionSimulation
    {
        return SectionSimulation::query()
            ->where('tenant_id', $this->tenant()->id)
            ->where('name', 'Section A Demo Halden Energy')
            ->firstOrFail();
    }

    private function week4Definition(): SimulationWeek
    {
        $week = SimulationWeek::query()
            ->where('simulation_version_id', $this->sectionSimulation()->simulation_version_id)
            ->where('week_number', 4)
            ->firstOrFail();

        if ($week->week_number !== 4) {
            throw new InvalidArgumentException('Demo Week 4 definition is not week 4.');
        }

        return $week;
    }

    private function week4Runtime(): SectionSimulationWeek
    {
        return SectionSimulationWeek::query()
            ->where('tenant_id', $this->tenant()->id)
            ->where('section_simulation_id', $this->sectionSimulation()->id)
            ->where('simulation_week_id', $this->week4Definition()->id)
            ->firstOrFail();
    }

    private function contentPackage(): SimulationContentPackage
    {
        return SimulationContentPackage::query()
            ->where('simulation_version_id', $this->week4Definition()->simulation_version_id)
            ->where('simulation_week_id', $this->week4Definition()->id)
            ->where('package_type', Week4ContentPackageRegistrationService::PACKAGE_TYPE)
            ->where('version', Week4ContentPackageRegistrationService::PACKAGE_VERSION)
            ->firstOrFail();
    }

    private function resolvedPackageVersion(): string
    {
        $package = app(SimulationContentResolver::class)->activePackageFor($this->week4Runtime());

        return $package->version;
    }

    private function decisionDefinition(): DecisionFormDefinition
    {
        return DecisionFormDefinition::query()
            ->where('simulation_version_id', $this->week4Definition()->simulation_version_id)
            ->where('simulation_week_id', $this->week4Definition()->id)
            ->where('key', 'week4_transfer_pricing')
            ->firstOrFail();
    }

    private function memoDefinition(): MemoDefinition
    {
        return MemoDefinition::query()
            ->where('simulation_version_id', $this->week4Definition()->simulation_version_id)
            ->where('simulation_week_id', $this->week4Definition()->id)
            ->where('key', 'week4_transfer_pricing_memo')
            ->firstOrFail();
    }

    private function serviceBindings(): string
    {
        app(WeekExecutionService::class);
        app(Week4KpiPopulationService::class);
        app(InterpretiveAssistantService::class);

        return 'registered';
    }
}
