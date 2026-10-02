<?php

namespace Tests\Feature\CohortFeedback;

use App\Domain\CohortFeedback\Window1CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window3CohortResponseFunctionCatalog;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week3\Week3EconomicEngine;
use App\Domain\Economics\Week5\Week5EconomicEngine;
use App\Domain\Economics\Week7\Week7EconomicEngine;
use App\Domain\Economics\Week9\Week9EconomicEngine;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week3EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week7EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Window1Window3RuntimeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week3_window1_effect_flows_into_week5_evaluation_snapshot(): void
    {
        $context = $this->runtimeContext([3, 5]);
        $this->submitDecision($context['weeks'][3], $context['teamSimulation'], 'week3_shutdown_point', Week3EconomicEngine::ENGINE_VERSION, Week3EconomicEngine::ENGINE_IDENTIFIER, [
            'european_utilization' => '0.90',
            'rotterdam_posture' => 'run',
        ]);
        $this->submitDecision($context['weeks'][5], $context['teamSimulation'], 'week5_currency_exposure', Week5EconomicEngine::ENGINE_VERSION, Week5EconomicEngine::ENGINE_IDENTIFIER, [
            'crude_hedge_coverage' => '0.65',
        ]);

        $this->closeWeek($context['weeks'][3], $context['graph']['faculty']);

        $week3Record = app(WeekExecutionService::class)->execute($context['weeks'][3]->refresh(), $context['graph']['faculty'], $context['packageTypes'][3]);
        $this->assertSame('completed', $week3Record->status, (string) $week3Record->failure_message);
        $this->assertSame(1, Week3EconomicEvaluation::query()->count());

        $effect = CohortFeedbackEffect::query()->where('target_section_simulation_week_id', $context['weeks'][5]->id)->firstOrFail();
        $this->assertSame('3.250000', $effect->effect_snapshot['response']['bounded_value']);

        $week5Record = app(WeekExecutionService::class)->execute($context['weeks'][5]->refresh(), $context['graph']['faculty'], $context['packageTypes'][5]);
        $this->assertSame('completed', $week5Record->status, (string) $week5Record->failure_message);

        $evaluation = Week5EconomicEvaluation::query()->firstOrFail();
        $this->assertSame('3.250000', $evaluation->output_snapshot['nwe_crack_handoff']['final_nwe_crack']);
        $this->assertSame($effect->id, $evaluation->output_snapshot['nwe_crack_handoff']['cohort_feedback_effect_id']);
    }

    public function test_week7_window3_effect_flows_into_week9_evaluation_snapshot(): void
    {
        $context = $this->runtimeContext([7, 9]);
        $this->submitDecision($context['weeks'][7], $context['teamSimulation'], 'week7_competitive_response', Week7EconomicEngine::ENGINE_VERSION, Week7EconomicEngine::ENGINE_IDENTIFIER, [
            'retail_pricing_aggression' => '0.20',
            'capacity_response' => 'hold',
        ]);
        $this->submitDecision($context['weeks'][9], $context['teamSimulation'], 'week9_cordell_rebrand', Week9EconomicEngine::ENGINE_VERSION, Week9EconomicEngine::ENGINE_IDENTIFIER, [
            'rebrand_LA_MS_core' => false,
            'rebrand_gulf_secondary' => true,
            'rebrand_southeast_edge' => true,
            'nonfuel_state_key' => 'base',
        ]);

        $this->closeWeek($context['weeks'][7], $context['graph']['faculty']);

        $week7Record = app(WeekExecutionService::class)->execute($context['weeks'][7]->refresh(), $context['graph']['faculty'], $context['packageTypes'][7]);
        $this->assertSame('completed', $week7Record->status, (string) $week7Record->failure_message);
        $this->assertSame(1, Week7EconomicEvaluation::query()->count());

        $effect = CohortFeedbackEffect::query()->where('target_section_simulation_week_id', $context['weeks'][9]->id)->firstOrFail();
        $this->assertSame('0.450000', $effect->effect_snapshot['response']['bounded_value']);

        $week9Record = app(WeekExecutionService::class)->execute($context['weeks'][9]->refresh(), $context['graph']['faculty'], $context['packageTypes'][9]);
        $this->assertSame('completed', $week9Record->status, (string) $week9Record->failure_message);

        $evaluation = Week9EconomicEvaluation::query()->firstOrFail();
        $this->assertSame('0.450000', $evaluation->output_snapshot['nonfuel_margin_handoff']['final_nonfuel_margin']);
        $this->assertSame($effect->id, $evaluation->output_snapshot['nonfuel_margin_handoff']['cohort_feedback_effect_id']);
    }

    public function test_window1_and_window3_are_excluded_from_seven_week_variant(): void
    {
        $graph = $this->tenantGraph('SevenWeekWindows');
        $structure = $this->simulationStructure(7);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);

        $this->assertSame(7, $sectionSimulation->version->variant->duration_weeks);

        $week3 = $sectionSimulation->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 3))->firstOrFail();
        $week7 = $sectionSimulation->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 7))->firstOrFail();
        $service = app(WeekExecutionService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cohortFunctionExcludedForRuntime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, Window1CohortResponseFunctionCatalog::fromRepository()->register(), $week3));
        $this->assertTrue($method->invoke($service, Window3CohortResponseFunctionCatalog::fromRepository()->register(), $week7));
    }

    /**
     * @param  list<int>  $weekNumbers
     * @return array{graph: array<string, mixed>, sectionSimulation: SectionSimulation, weeks: array<int, SectionSimulationWeek>, packageTypes: array<int, string>, teamSimulation: TeamSimulation}
     */
    private function runtimeContext(array $weekNumbers): array
    {
        $graph = $this->tenantGraph('Windows'.implode('', $weekNumbers));
        $structure = $this->simulationStructure(9);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $manifest = app(AuthoritativeContentPackageManifest::class);
        $packageTypes = [];
        Window1CohortResponseFunctionCatalog::fromRepository()->register();
        Window3CohortResponseFunctionCatalog::fromRepository()->register();

        foreach ($weekNumbers as $weekNumber) {
            /** @var SimulationWeek $definition */
            $definition = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);
            $packageTypes[$weekNumber] = $manifest->packageType($weekNumber);
            app(SimulationContentActivationService::class)->activate(
                app(AuthoritativeContentPackageRegistrationService::class)->register($definition, 'window-runtime-'.$weekNumber),
            );
        }

        $weeks = [];
        foreach ($weekNumbers as $weekNumber) {
            $weeks[$weekNumber] = $sectionSimulation->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))->firstOrFail();
        }

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()->where('team_id', $graph['team']->id)->firstOrFail();

        return compact('graph', 'sectionSimulation', 'weeks', 'packageTypes', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function submitDecision(
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        string $key,
        string $version,
        string $engine,
        array $answers,
    ): DecisionSubmission {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => $key,
            'name' => $key,
            'version' => $version,
            'metadata' => ['economic_engine' => $engine],
        ]);

        MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => $key.'_memo',
            'title' => $key.' memo',
            'version' => $version,
        ]);

        $submission = new DecisionSubmission([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted,
            'answers' => $answers,
            'submitted_at' => now(),
        ]);
        $submission->save();

        return $submission;
    }

    private function closeWeek(SectionSimulationWeek $runtimeWeek, $faculty): void
    {
        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Released, $faculty);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $faculty, now()->addDay());
        $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Closed, $faculty);
    }
}
