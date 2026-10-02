<?php

namespace App\Domain\Economics\Week4;

use App\Models\DecisionSubmission;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class Week4ResolutionInputMapper
{
    public function __construct(
        private readonly Week4EconomicEngine $engine,
        private readonly Week4ReferencePackage $package,
    ) {}

    public function map(DecisionSubmission $submission): Week4MappedDecision
    {
        $answers = $submission->getAttribute('answers');
        if (! is_array($answers)) {
            throw new InvalidArgumentException('Week 4 decision submission has no answer payload.');
        }

        $submittedAt = $submission->getAttribute('submitted_at');
        $inputs = $this->package->inputs();
        $transferPrice = $this->selectedTransferPrice($answers, $inputs);

        return new Week4MappedDecision(
            inputs: $inputs,
            transferPrice: $transferPrice,
            inputSnapshot: [
                'reference_package' => $this->package->inputSnapshot(),
                'selected_transfer_price' => (string) $transferPrice,
            ],
            submissionSnapshot: [
                'decision_submission_id' => $submission->id,
                'decision_form_definition_id' => $submission->decision_form_definition_id,
                'answers' => $answers,
                'submitted_at' => $submittedAt instanceof CarbonInterface ? $submittedAt->toISOString() : $submittedAt,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function selectedTransferPrice(array $answers, Week4EconomicInputs $inputs): BigDecimal
    {
        if (array_key_exists('transfer_price', $answers) && $answers['transfer_price'] !== null && $answers['transfer_price'] !== '') {
            return BigDecimal::of((string) $answers['transfer_price']);
        }

        if (array_key_exists('transfer_price_anchor', $answers) && is_string($answers['transfer_price_anchor'])) {
            return $this->transferPriceForAnchor($answers['transfer_price_anchor'], $inputs);
        }

        if (array_key_exists('transfer_price_option', $answers) && is_string($answers['transfer_price_option'])) {
            return $this->transferPriceForAnchor($answers['transfer_price_option'], $inputs);
        }

        throw new InvalidArgumentException('Week 4 decision submission must include a transfer price decision.');
    }

    private function transferPriceForAnchor(string $anchor, Week4EconomicInputs $inputs): BigDecimal
    {
        $prices = $this->engine->transferPrices($inputs);

        return match ($anchor) {
            'market', 'market_based' => $prices->market,
            'marginal_cost', 'marginal' => $prices->marginalCost,
            'lazy_midpoint', 'midpoint' => $prices->lazyMidpoint,
            default => throw new InvalidArgumentException("Unknown Week 4 transfer price anchor [{$anchor}]."),
        };
    }
}
