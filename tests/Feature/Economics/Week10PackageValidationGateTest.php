<?php

namespace Tests\Feature\Economics;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Economics\Week10\Week10ReferencePackage;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week10PackageValidationGateTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week10_reference_package_loads_canonical_inputs_and_provenance(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();

        $this->assertSame('1.0.1', $inputs->packageVersion);
        $this->assertSame(['gasoline', 'diesel', 'jet'], array_keys($inputs->productElasticities));
        $this->assertSame(['Baton Rouge', 'Rotterdam', 'Singapore'], array_keys($inputs->refineryYields));
        $this->assertSame(['reference_disciplined', 'reference_constrained'], array_keys($inputs->teamPriorStates));
        $this->assertDecimalClose('0.35', $inputs->productElasticity('gasoline'));
        $this->assertDecimalClose('1.6', $inputs->productElasticity('jet'));
        $this->assertDecimalClose('-0.03', $inputs->recessionParameter('gdp_change'));
        $this->assertDecimalClose('0.3', $inputs->refineryYield('Singapore', 'jet'));
        $this->assertDecimalClose('200.0', $inputs->bindingRule('min_cancellable_capex_musd'));
        $this->assertDecimalClose('0.5', $inputs->bindingRule('min_crude_hedge_coverage'));
        $this->assertDecimalClose('150.0', $inputs->bindingRule('min_cash_cushion_musd'));
        $this->assertArrayHasKey('fixtures/week10_golden.json', $inputs->sourceHashes);
        $this->assertSame(
            hash_file('sha256', base_path('halden-week10-data-package/fixtures/week10_golden.json')),
            $inputs->sourceHashes['fixtures/week10_golden.json'],
        );
    }

    public function test_week10_golden_outputs_are_available_without_implementing_engine_math(): void
    {
        $golden = Week10ReferencePackage::fromRepository()->inputs()->golden;
        $results = $golden['results'];

        $this->assertSame(10, $golden['meta']['week']);
        $this->assertSame('1.0.1', $golden['meta']['package_version']);
        $this->assertSame(0.001, $golden['meta']['tolerance']['rel']);
        $this->assertSame(1e-5, $golden['meta']['tolerance']['abs']);
        $this->assertSame(-0.0105, $results['demand_hit_gasoline']);
        $this->assertSame(-0.0255, $results['demand_hit_diesel']);
        $this->assertSame(-0.048, $results['demand_hit_jet']);
        $this->assertSame(-0.0237, $results['blended_demand_hit']);
        $this->assertSame(-0.01992, $results['refinery_hit_br']);
        $this->assertSame(-0.02226, $results['refinery_hit_rot']);
        $this->assertSame(-0.02622, $results['refinery_hit_sing']);
        $this->assertSame('Singapore', $results['hardest_hit_refinery']);
        $this->assertSame(0, $results['disciplined_binding_count']);
        $this->assertSame(5, $results['constrained_binding_count']);
        $this->assertTrue(collect($golden['ordering_assertions'])->every(fn (array $assertion): bool => $assertion['holds'] === true));
    }

    public function test_week10_prior_state_rows_are_fixture_references_not_runtime_team_state(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $disciplined = $inputs->priorState('reference_disciplined');
        $constrained = $inputs->priorState('reference_constrained');

        $this->assertDecimalClose('310.0', $disciplined['cancellable_capex_musd']);
        $this->assertDecimalClose('0.7', $disciplined['crude_hedge_coverage']);
        $this->assertFalse($disciplined['br_reported_margin_strong']);
        $this->assertSame('cooperative', $disciplined['straits_pacific_standing']);
        $this->assertDecimalClose('240.0', $disciplined['cash_cushion_musd']);

        $this->assertDecimalClose('120.0', $constrained['cancellable_capex_musd']);
        $this->assertDecimalClose('0.45', $constrained['crude_hedge_coverage']);
        $this->assertTrue($constrained['br_reported_margin_strong']);
        $this->assertSame('strained', $constrained['straits_pacific_standing']);
        $this->assertDecimalClose('85.0', $constrained['cash_cushion_musd']);
    }

    public function test_week10_runtime_dependencies_and_decision_structure_are_documented_for_next_batch(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();

        $this->assertSame(
            [
                'cancellable_capex_musd',
                'crude_hedge_coverage',
                'br_reported_margin_strong',
                'straits_pacific_standing',
                'cash_cushion_musd',
            ],
            array_keys($inputs->runtimeDependencies),
        );
        $this->assertSame('6', $inputs->runtimeDependencies['cancellable_capex_musd']['source_week']);
        $this->assertSame('4', $inputs->runtimeDependencies['br_reported_margin_strong']['source_week']);
        $this->assertSame('standing_history', $inputs->runtimeDependencies['straits_pacific_standing']['source_week']);
        $this->assertSame('8', $inputs->runtimeDependencies['cash_cushion_musd']['source_week']);

        $decisionKeys = collect($inputs->decisionStructure)->pluck('key')->all();

        $this->assertSame(
            [
                'run_rate_baton_rouge_pct',
                'run_rate_rotterdam_pct',
                'run_rate_singapore_pct',
                'capital_response',
                'hedge_response',
                'working_capital_release_musd',
                'binding_constraint_explanation',
            ],
            $decisionKeys,
        );
    }

    public function test_week10_package_registration_preserves_artifact_boundary(): void
    {
        $structure = $this->simulationStructure(14);
        $week10 = $structure['simulationWeeks']->firstWhere('week_number', 10);

        $package = app(AuthoritativeContentPackageRegistrationService::class)->register($week10);

        $this->assertSame(app(AuthoritativeContentPackageManifest::class)->packageType(10), $package->package_type);
        $this->assertSame(10, $package->manifest['week_number']);
        $this->assertSame('authoritative_package_available', $package->manifest['status']);
        $this->assertSame('week10_fixtures_week10_golden_json', $package->artifacts()->where('artifact_type', 'expected_outputs')->firstOrFail()->artifact_key);
        $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());
    }

    private function assertDecimalClose(string $expected, mixed $actual): void
    {
        $actualDecimal = $actual instanceof BigDecimal ? $actual : BigDecimal::of((string) $actual);
        $expectedDecimal = BigDecimal::of($expected);
        $difference = $actualDecimal->minus($expectedDecimal);

        if ($difference->isLessThan(BigDecimal::zero())) {
            $difference = $difference->negated();
        }

        $relativeTolerance = $this->absolute($expectedDecimal)->multipliedBy('0.001');
        $absoluteTolerance = BigDecimal::of('0.00001');
        $tolerance = $relativeTolerance->isGreaterThan($absoluteTolerance)
            ? $relativeTolerance
            : $absoluteTolerance;

        $this->assertTrue(
            $difference->isLessThanOrEqualTo($tolerance),
            "Expected {$actualDecimal} to be within {$tolerance} of {$expectedDecimal}; difference {$difference}.",
        );
    }

    private function absolute(BigDecimal $value): BigDecimal
    {
        return $value->isLessThan(BigDecimal::zero()) ? $value->negated() : $value;
    }
}
