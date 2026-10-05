<?php

namespace App\Domain\Consequences;

use App\Domain\Standing\StandingService;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationEvaluation;
use App\Models\ConsequenceDefinition;
use App\Models\ConsequenceLink;
use App\Models\Counterparty;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DerivedWeek10ConstraintService
{
    public function __construct(
        private ConsequenceService $consequences,
        private KpiConsequenceDefinitionCatalog $definitions,
    ) {}

    public function resolveWeek4Consequences(EconomicResolution $resolution, ?User $actor = null): void
    {
        $resolution->loadMissing(['runtimeWeek.definition', 'teamSimulation']);

        if ($resolution->runtimeWeek->definition->week_number !== 4) {
            throw new InvalidArgumentException('Only Week 4 resolutions can produce Week 10 Week 4-derived constraints.');
        }

        DB::transaction(function () use ($resolution, $actor): void {
            $week10 = $this->runtimeWeek($resolution->teamSimulation, 10);
            $cover = BigDecimal::of((string) $resolution->refining_vs_target)->isGreaterThan(BigDecimal::zero());
            $standing = $this->whitakerStandingFor($resolution);
            $standingState = $this->standingState(
                teamSimulation: $resolution->teamSimulation,
                counterpartyKey: 'whitaker',
                state: $standing,
                reason: 'Week 4 transfer-pricing consequence from authoritative KPI/consequence package.',
                runtimeWeek: $resolution->runtimeWeek,
                trigger: $resolution,
            );

            $this->firstOrCreateLink(
                teamSimulation: $resolution->teamSimulation,
                definition: $this->definitions->delacroixCover(),
                source: $resolution,
                target: $resolution,
                sourceWeek: $resolution->runtimeWeek,
                targetWeek: $week10,
                explanation: 'Week 4 refining segment margin determines whether Delacroix has Week 10 cover.',
                actor: $actor,
                metadata: [
                    'target_value' => $cover ? '1' : '0',
                    'constraint_key' => 'br_reported_margin_strong',
                    'source_metric' => 'refining_vs_target',
                    'source_value' => (string) $resolution->refining_vs_target,
                    'rule' => 'cover = 1 when refining reports above target; otherwise 0',
                    'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
                ],
            );

            $this->firstOrCreateLink(
                teamSimulation: $resolution->teamSimulation,
                definition: $this->definitions->whitakerStanding(),
                source: $resolution,
                target: $standingState,
                sourceWeek: $resolution->runtimeWeek,
                targetWeek: $this->runtimeWeek($resolution->teamSimulation, 5) ?? $week10,
                explanation: 'Week 4 transfer-pricing posture sets Whitaker standing for downstream hedge coverage.',
                actor: $actor,
                metadata: [
                    'target_value' => $standing->value,
                    'counterparty' => 'whitaker',
                    'source_metric' => 'geneva_capture_per_bbl',
                    'source_value' => (string) $resolution->geneva_capture_per_bbl,
                    'rule' => 'Geneva leak -> guarded; otherwise cooperative',
                    'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
                ],
            );

            $this->resolveHedgeCoverage($resolution->teamSimulation, $actor);
        });
    }

    public function resolveHedgeCoverage(TeamSimulation $teamSimulation, ?User $actor = null): ?ConsequenceLink
    {
        $standing = $this->standingFor($teamSimulation, 'whitaker');

        if (! $standing instanceof StandingState) {
            return null;
        }

        $coverage = $this->hedgeCoverageForStanding($standing->stateEnum());

        return $this->firstOrCreateLink(
            teamSimulation: $teamSimulation,
            definition: $this->definitions->hedgeCoverage(),
            source: $standing,
            target: $standing,
            sourceWeek: $this->runtimeWeek($teamSimulation, 5) ?? $this->runtimeWeek($teamSimulation, 4),
            targetWeek: $this->runtimeWeek($teamSimulation, 10),
            explanation: 'Whitaker standing determines crude hedge coverage for Week 10 inherited state.',
            actor: $actor,
            metadata: [
                'target_value' => $this->decimal($coverage, 6),
                'constraint_key' => 'crude_hedge_coverage',
                'standing_state' => $standing->stateEnum()->value,
                'rule' => 'cooperative/obliged=0.70; watchful/guarded=0.45; strained/hostile=0.25',
                'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
            ],
        );
    }

    public function resolveCancellableCapex(CapitalAllocationEvaluation $evaluation, ?User $actor = null): ?ConsequenceLink
    {
        if ($evaluation->status !== CapitalAllocationEvaluation::STATUS_CALCULATED) {
            return null;
        }

        $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);
        $discount = DiscountRateConsequence::query()
            ->where('tenant_id', $evaluation->tenant_id)
            ->where('target_section_simulation_week_id', $evaluation->section_simulation_week_id)
            ->where('team_simulation_id', $evaluation->team_simulation_id)
            ->where('status', DiscountRateConsequence::STATUS_RESOLVED)
            ->latest('resolved_at')
            ->first();

        if (! $discount instanceof DiscountRateConsequence || $discount->capitalEnvelopeMusdValue() === null) {
            return null;
        }

        $selected = $this->selectedProjectKeys($evaluation);
        $helixFunded = in_array('helix', $selected, true);
        $envelope = BigDecimal::of($discount->capitalEnvelopeMusdValue());
        $cancellable = $envelope
            ->minus($helixFunded ? '880' : '0')
            ->multipliedBy('0.30');

        return $this->firstOrCreateLink(
            teamSimulation: $teamSimulation,
            definition: $this->definitions->cancellableCapex(),
            source: $evaluation,
            target: $evaluation,
            sourceWeek: SectionSimulationWeek::query()->find($evaluation->section_simulation_week_id),
            targetWeek: $this->runtimeWeek($teamSimulation, 10),
            explanation: 'Week 6 capital envelope and Helix commitment determine cancellable capex for Week 10.',
            actor: $actor,
            metadata: [
                'target_value' => $this->decimal($cancellable, 3),
                'constraint_key' => 'cancellable_capex_musd',
                'capital_envelope_musd' => $discount->capitalEnvelopeMusdValue(),
                'helix_funded' => $helixFunded,
                'selected_project_keys' => $selected,
                'rule' => '(capital envelope - 880 if Helix funded) * 0.30',
                'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
            ],
        );
    }

    public function resolveCashCushion(TeamSimulation $teamSimulation, ?User $actor = null): ?ConsequenceLink
    {
        $week8 = $this->runtimeWeek($teamSimulation, 8);

        if (! $week8 instanceof SectionSimulationWeek) {
            return null;
        }

        $evaluation = Week8EconomicEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week8->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', Week8EconomicEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();

        if (! $evaluation instanceof Week8EconomicEvaluation) {
            return null;
        }

        $week8EbitdaEffect = $this->week8EbitdaEffect($evaluation);
        $week6Outlay = $this->week6Outlay($teamSimulation);
        $week7CapacityMatch = $this->week7CapacityMatch($teamSimulation);
        $week9RebrandCost = $this->week9RebrandCost($teamSimulation);
        $cash = BigDecimal::of('250')
            ->plus($week8EbitdaEffect->multipliedBy('0.25'))
            ->minus(
                $week6Outlay
                    ->plus(BigDecimal::of('600')->multipliedBy($week7CapacityMatch))
                    ->plus($week9RebrandCost)
                    ->multipliedBy('0.10')
            );

        return $this->firstOrCreateLink(
            teamSimulation: $teamSimulation,
            definition: $this->definitions->cashCushion(),
            source: $evaluation,
            target: $evaluation,
            sourceWeek: $week8,
            targetWeek: $this->runtimeWeek($teamSimulation, 10),
            explanation: 'Earlier evaluated state determines Week 10 cash cushion.',
            actor: $actor,
            metadata: [
                'target_value' => $this->decimal($cash, 3),
                'constraint_key' => 'cash_cushion_musd',
                'week8_ebitda_effect_musd' => $this->decimal($week8EbitdaEffect, 3),
                'week6_outlay_musd' => $this->decimal($week6Outlay, 3),
                'week7_capacity_match' => $this->decimal($week7CapacityMatch, 3),
                'week9_rebrand_cost_musd' => $this->decimal($week9RebrandCost, 3),
                'seven_week_absent_terms_zero' => $this->isSevenWeekVariant($teamSimulation),
                'rule' => '250 + 0.25*W8 EBITDA effect - 0.10*(W6 outlay + 600*W7 capacity match + W9 rebrand cost)',
                'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
            ],
        );
    }

    public function resolveStraitsPacificFlex(TeamSimulation $teamSimulation, ?User $actor = null): ?ConsequenceLink
    {
        $standing = $this->standingFor($teamSimulation, 'straits_pacific');

        if (! $standing instanceof StandingState) {
            return null;
        }

        $binding = in_array($standing->stateEnum(), [StandingValue::Strained, StandingValue::Hostile], true);

        return $this->firstOrCreateLink(
            teamSimulation: $teamSimulation,
            definition: $this->definitions->straitsPacificFlex(),
            source: $standing,
            target: $standing,
            sourceWeek: null,
            targetWeek: $this->runtimeWeek($teamSimulation, 10),
            explanation: 'Straits Pacific qualitative standing determines whether the Week 10 flex constraint binds.',
            actor: $actor,
            metadata: [
                'target_value' => $binding ? '1' : '0',
                'constraint_key' => 'straits_pacific_standing',
                'standing_state' => $standing->stateEnum()->value,
                'rule' => 'binding when standing is strained or hostile',
                'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
            ],
        );
    }

    public function resolveAllForWeek10(TeamSimulation $teamSimulation, ?User $actor = null): void
    {
        $this->resolveHedgeCoverage($teamSimulation, $actor);

        $evaluation = $this->latestCapitalEvaluation($teamSimulation);
        if ($evaluation instanceof CapitalAllocationEvaluation) {
            $this->resolveCancellableCapex($evaluation, $actor);
        }

        $this->resolveCashCushion($teamSimulation, $actor);
        $this->resolveStraitsPacificFlex($teamSimulation, $actor);
    }

    public function latestConsequenceValue(TeamSimulation $teamSimulation, string $definitionKey): ?ConsequenceLink
    {
        return ConsequenceLink::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('definition_key', $definitionKey)
            ->latest('occurred_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function firstOrCreateLink(
        TeamSimulation $teamSimulation,
        ConsequenceDefinition $definition,
        Model $source,
        Model $target,
        ?SectionSimulationWeek $sourceWeek,
        ?SectionSimulationWeek $targetWeek,
        string $explanation,
        ?User $actor,
        array $metadata,
    ): ConsequenceLink {
        $existing = ConsequenceLink::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('definition_key', $definition->key)
            ->where('definition_version', $definition->version)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing instanceof ConsequenceLink) {
            return $existing;
        }

        return $this->consequences->createLink(
            teamSimulation: $teamSimulation,
            definition: $definition,
            source: $source,
            target: $target,
            explanation: $explanation,
            sourceWeek: $sourceWeek,
            targetWeek: $targetWeek,
            actor: $actor,
            metadata: [
                ...$metadata,
                'package_version' => KpiConsequenceReferencePackage::fromRepository()->version(),
                'resolved_at' => Carbon::now()->toISOString(),
            ],
        );
    }

    private function standingState(
        TeamSimulation $teamSimulation,
        string $counterpartyKey,
        StandingValue $state,
        string $reason,
        ?SectionSimulationWeek $runtimeWeek,
        Model $trigger,
    ): StandingState {
        $counterparty = Counterparty::query()->firstOrCreate(['key' => $counterpartyKey], [
            'name' => str($counterpartyKey)->replace('_', ' ')->title()->toString(),
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => [],
        ]);

        return app(StandingService::class)
            ->applyChange($teamSimulation, $counterparty, $state, $reason, $runtimeWeek, $trigger);
    }

    private function standingFor(TeamSimulation $teamSimulation, string $counterpartyKey): ?StandingState
    {
        $counterparty = Counterparty::query()->where('key', $counterpartyKey)->first();

        if (! $counterparty instanceof Counterparty) {
            return null;
        }

        return StandingState::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('counterparty_id', $counterparty->id)
            ->first();
    }

    private function whitakerStandingFor(EconomicResolution $resolution): StandingValue
    {
        return BigDecimal::of((string) $resolution->geneva_capture_per_bbl)->isGreaterThan(BigDecimal::zero())
            ? StandingValue::Guarded
            : StandingValue::Cooperative;
    }

    private function hedgeCoverageForStanding(StandingValue $standing): BigDecimal
    {
        return match ($standing) {
            StandingValue::Cooperative, StandingValue::Obliged => BigDecimal::of('0.70'),
            StandingValue::Watchful, StandingValue::Guarded => BigDecimal::of('0.45'),
            StandingValue::Strained, StandingValue::Hostile => BigDecimal::of('0.25'),
        };
    }

    /**
     * @return list<string>
     */
    private function selectedProjectKeys(CapitalAllocationEvaluation $evaluation): array
    {
        $output = $evaluation->getAttribute('output_snapshot');

        if (is_array($output) && is_array($output['selected_project_keys'] ?? null)) {
            return array_values(array_map('strval', $output['selected_project_keys']));
        }

        $input = $evaluation->getAttribute('input_snapshot');
        $projects = is_array($input) && is_array($input['selected_projects'] ?? null)
            ? $input['selected_projects']
            : [];

        return array_values(array_filter(array_map(
            fn (mixed $project): ?string => is_array($project) && is_string($project['key'] ?? null) ? $project['key'] : null,
            $projects,
        )));
    }

    private function latestCapitalEvaluation(TeamSimulation $teamSimulation): ?CapitalAllocationEvaluation
    {
        $week6 = $this->runtimeWeek($teamSimulation, 6);

        if (! $week6 instanceof SectionSimulationWeek) {
            return null;
        }

        return CapitalAllocationEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week6->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', CapitalAllocationEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();
    }

    private function week8EbitdaEffect(Week8EconomicEvaluation $evaluation): BigDecimal
    {
        $realization = $evaluation->getAttribute('realization_snapshot');
        $deltaWti = is_array($realization) && isset($realization['delta_wti'])
            ? BigDecimal::of((string) $realization['delta_wti'])
            : BigDecimal::of((string) $evaluation->realized_upstream_impact_per_bbl);

        // Package-backed bridge: integrated margin moves 0.65 per $1 WTI on the 250 kb/d Permian-Baton Rouge chain.
        return $deltaWti->multipliedBy('0.65')->multipliedBy('91.25');
    }

    private function week6Outlay(TeamSimulation $teamSimulation): BigDecimal
    {
        $evaluation = $this->latestCapitalEvaluation($teamSimulation);

        if (! $evaluation instanceof CapitalAllocationEvaluation || $evaluation->capitalRequiredMusdValue() === null) {
            return BigDecimal::zero();
        }

        return BigDecimal::of($evaluation->capitalRequiredMusdValue());
    }

    private function week7CapacityMatch(TeamSimulation $teamSimulation): BigDecimal
    {
        $week7 = $this->runtimeWeek($teamSimulation, 7);

        if (! $week7 instanceof SectionSimulationWeek) {
            return BigDecimal::zero();
        }

        $submission = DecisionSubmission::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week7->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->latest('submitted_at')
            ->first();

        $answers = $submission?->getAttribute('answers');

        return is_array($answers) && ($answers['capacity_response'] ?? null) === 'match'
            ? BigDecimal::one()
            : BigDecimal::zero();
    }

    private function week9RebrandCost(TeamSimulation $teamSimulation): BigDecimal
    {
        $week9 = $this->runtimeWeek($teamSimulation, 9);

        if (! $week9 instanceof SectionSimulationWeek) {
            return BigDecimal::zero();
        }

        $evaluation = Week9EconomicEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week9->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', Week9EconomicEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();

        if (! $evaluation instanceof Week9EconomicEvaluation) {
            return BigDecimal::zero();
        }

        $snapshot = $evaluation->getAttribute('output_snapshot');
        $cost = is_array($snapshot) ? ($snapshot['selected_rebrand_cost_musd'] ?? $snapshot['partial_cost_musd'] ?? null) : null;

        return $cost === null ? BigDecimal::zero() : BigDecimal::of((string) $cost);
    }

    private function runtimeWeek(TeamSimulation $teamSimulation, int $weekNumber): ?SectionSimulationWeek
    {
        return SectionSimulationWeek::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_id', $teamSimulation->section_simulation_id)
            ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
            ->first();
    }

    private function isSevenWeekVariant(TeamSimulation $teamSimulation): bool
    {
        $teamSimulation->loadMissing('sectionSimulation.version.variant');

        return (int) $teamSimulation->sectionSimulation->version->variant?->duration_weeks === 7;
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function decimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
