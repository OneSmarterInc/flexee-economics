<?php

namespace Tests\Unit\OperatingModel;

use App\Halden\OperatingModel\Decisions;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use App\Halden\OperatingModel\Scoring;
use Tests\TestCase;

/**
 * The PHP engine must reproduce every number in packages/operating-model/fixtures/golden_quarters.csv,
 * which the Python reference model wrote. Tolerance: relative 1e-6, absolute 1e-4.
 */
class GoldenQuartersTest extends TestCase
{
    private function fixtures(): array
    {
        $golden = [];
        foreach (ModelData::csv(base_path('packages/operating-model/fixtures/golden_quarters.csv')) as $r) {
            $golden[$r['team']][$r['quarter']][$r['metric']] = (float) $r['value'];
        }

        return $golden;
    }

    private function assertClose(float $expected, float $actual, string $label): void
    {
        $tol = max(1e-4, abs($expected) * 1e-6);
        $this->assertLessThanOrEqual($tol, abs($expected - $actual), "$label: expected $expected, got $actual");
    }

    public function test_history_quarters_match_the_reference_model(): void
    {
        $golden = $this->fixtures();
        $model = new OperatingModel(new ModelData);
        [, $history] = $model->runHistory();

        $this->assertCount(4, $history);
        foreach ($history as $h) {
            foreach ($golden['history'][$h['quarter']['quarter']] as $metric => $expected) {
                $actual = $h['result']->metrics()[$metric] ?? null;
                $this->assertNotNull($actual, "missing $metric");
                $this->assertClose($expected, $actual, "history {$h['quarter']['quarter']} $metric");
            }
        }
        $this->assertClose(3750.0, $history[3]['result']->money['ebitda'], 'Q4 2026 EBITDA calibration');
    }

    public function test_reference_teams_match_the_reference_model_every_quarter(): void
    {
        $golden = $this->fixtures();
        $data = new ModelData;
        $model = new OperatingModel($data);
        [$start] = $model->runHistory();

        $plans = [];
        $quarters = [];
        foreach (ModelData::csv(base_path('packages/operating-model/fixtures/reference_decisions.csv')) as $row) {
            $plans[$row['team']][(int) $row['round']] = Decisions::fromRow($row, $data);
            $quarters[(int) $row['round']] = $row['quarter'];
        }
        ksort($quarters);
        $effects = json_decode((string) file_get_contents(base_path('packages/operating-model/fixtures/class_effects.json')), true, flags: JSON_THROW_ON_ERROR);

        $states = array_map(fn () => clone $start, $plans);
        $checked = 0;
        foreach ($quarters as $round => $key) {
            $quarter = $data->quarter($key);
            if ($key === '2028Q1') {
                $util = array_sum(array_map(fn (array $byRound) => $model->europeanUtil($byRound[3]), $plans)) / count($plans);
                $quarter['nwe'] = $model->window1Nwe($util);
                $this->assertClose((float) $effects['window1_nwe'], $quarter['nwe'], 'Window 1 margin');
            }
            if ($key === '2028Q2') {
                $q4 = $data->quarter('2027Q4')['wti'];
                $avg = array_sum(array_map(fn (array $byRound) => $model->crudePriceDiscipline($byRound[4], $q4), $plans)) / count($plans);
                $terms = $model->capitalTerms($avg);
                $this->assertSame($effects['capital_behaviour'], $terms['behaviour']);
                $this->assertClose((float) $effects['capital_envelope'], $terms['envelope'], 'capital envelope');
            }
            $results = [];
            foreach ($plans as $team => $byRound) {
                $results[$team] = $model->step($states[$team], $byRound[$round], $quarter);
                $states[$team] = $results[$team]->state;
            }
            $scores = Scoring::composite(array_map(fn ($r) => $r->kpi, $results));
            $ranks = Scoring::rank($scores);

            foreach ($results as $team => $result) {
                $metrics = $result->metrics() + ['score.composite' => $scores[$team], 'score.rank' => (float) $ranks[$team]];
                foreach ($golden[$team][$key] as $metric => $expected) {
                    $this->assertArrayHasKey($metric, $metrics, "missing $metric");
                    $this->assertClose($expected, $metrics[$metric], "$team $key $metric");
                    $checked++;
                }
            }
        }
        $this->assertCount(6, $quarters);
        $this->assertGreaterThan(800, $checked);
    }
}
