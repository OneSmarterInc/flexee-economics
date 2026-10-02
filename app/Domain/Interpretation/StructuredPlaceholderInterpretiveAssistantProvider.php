<?php

namespace App\Domain\Interpretation;

final class StructuredPlaceholderInterpretiveAssistantProvider implements InterpretiveAssistantProvider
{
    public function providerName(): string
    {
        return 'structured_placeholder';
    }

    public function modelName(): string
    {
        return 'halden_interpretive_assistant_stub_v1';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{response: string, response_snapshot: array<string, mixed>}
     */
    public function generate(array $context, string $focus, string $promptVersion): array
    {
        $counts = [
            'decisions' => count($context['decisions'] ?? []),
            'memos' => count($context['memos'] ?? []),
            'economic_resolutions' => count($context['economic_resolutions'] ?? []),
            'kpi_snapshots' => count($context['kpi_snapshots'] ?? []),
            'ranking_snapshots' => count($context['ranking_snapshots'] ?? []),
            'standing_states' => count($context['standing_states'] ?? []),
            'standing_events' => count($context['standing_events'] ?? []),
            'consequence_links' => count($context['consequence_links'] ?? []),
            'advisor_consultations' => count($context['advisor_consultations'] ?? []),
            'alternatives' => count($context['alternatives'] ?? []),
        ];

        return [
            'response' => 'Interpretive assistant request queued and processed with structured Halden context. This foundation response does not assign grades, rank teams, or replace instructor judgment.',
            'response_snapshot' => [
                'focus' => $focus,
                'prompt_version' => $promptVersion,
                'record_counts' => $counts,
                'guardrails' => [
                    'no_grading_decisions',
                    'no_team_ranking_decisions',
                    'faculty_judgment_required',
                ],
            ],
        ];
    }
}
