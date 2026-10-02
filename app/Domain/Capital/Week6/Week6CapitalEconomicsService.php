<?php

namespace App\Domain\Capital\Week6;

use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week6CapitalEconomicsService
{
    public function __construct(
        private Week6CapitalEconomicsEngine $engine,
    ) {}

    public function evaluate(
        CapitalAllocationDecision $decision,
        User $actor,
        ?Week6CapitalReferencePackage $package = null,
        string $process = 'week6_capital_economics_service',
    ): CapitalAllocationEvaluation {
        $decision->loadMissing(['runtimeWeek.definition']);
        $this->assertCanEvaluate($actor, $decision);

        return DB::transaction(function () use ($decision, $actor, $package, $process): CapitalAllocationEvaluation {
            $existing = CapitalAllocationEvaluation::query()
                ->where('tenant_id', $decision->tenant_id)
                ->where('capital_allocation_decision_id', $decision->id)
                ->where('engine_identifier', Week6CapitalEconomicsEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CapitalAllocationEvaluation) {
                return $existing;
            }

            $result = $this->engine->evaluate($decision, $package);

            return CapitalAllocationEvaluation::query()->create([
                'tenant_id' => $decision->tenant_id,
                'section_simulation_id' => $decision->section_simulation_id,
                'section_simulation_week_id' => $decision->section_simulation_week_id,
                'team_simulation_id' => $decision->team_simulation_id,
                'team_id' => $decision->team_id,
                'capital_allocation_decision_id' => $decision->id,
                'engine_identifier' => Week6CapitalEconomicsEngine::ENGINE_IDENTIFIER,
                'engine_version' => Week6CapitalEconomicsEngine::ENGINE_VERSION,
                'status' => $result->status,
                'portfolio_npv_musd' => $result->portfolioNpvMusd,
                'portfolio_irr_percent' => $result->portfolioIrrPercent,
                'capital_required_musd' => $result->capitalRequiredMusd,
                'capital_envelope_feasible' => $result->capitalEnvelopeFeasible,
                'input_snapshot' => $result->inputSnapshot,
                'output_snapshot' => $result->outputSnapshot,
                'unavailable_reason' => $result->unavailableReason,
                'evaluated_by_user_id' => $actor->id,
                'evaluated_by_process' => $process,
                'evaluated_at' => Carbon::now(),
            ]);
        });
    }

    private function assertCanEvaluate(User $actor, CapitalAllocationDecision $decision): void
    {
        if ($decision->runtimeWeek->definition->week_number !== 6) {
            throw new InvalidArgumentException('Week 6 capital economics can only evaluate Week 6 allocation decisions.');
        }

        if ($actor->tenant_id !== $decision->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate capital economics for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $decision->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($decision->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 6 capital economics.');
    }
}
