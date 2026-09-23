<?php

namespace App\Domain\Advisors;

use App\Models\Advisor;
use Illuminate\Database\Eloquent\Collection;

final class AdvisorCatalog
{
    public const CONTENT_VERSION = 'halden_advisors_v1';

    /**
     * @return list<array<string, mixed>>
     */
    public function haldenAdvisors(): array
    {
        return [
            [
                'key' => 'elena_marchetti',
                'name' => 'Elena Marchetti',
                'title' => 'Chief Economist',
                'perspective' => 'Macroeconomic framing, commodity-cycle discipline, and second-order market effects.',
                'default_guidance' => 'Anchor the decision in the market path, identify the assumptions that matter most, and be explicit about uncertainty instead of treating a point forecast as destiny.',
                'sort_order' => 10,
            ],
            [
                'key' => 'danielle_roy',
                'name' => 'Danielle Roy',
                'title' => 'SVP Commercial',
                'perspective' => 'Customer economics, trading behavior, negotiated commitments, and margin capture.',
                'default_guidance' => 'Ask who captures value, who loses optionality, and whether the commercial logic still holds if counterparties respond strategically.',
                'sort_order' => 20,
            ],
            [
                'key' => 'bjorn_aasen',
                'name' => 'Bjørn Aasen',
                'title' => 'SVP Operations',
                'perspective' => 'Operational feasibility, asset reliability, execution constraints, and throughput risk.',
                'default_guidance' => 'Separate what looks attractive on paper from what the assets can actually execute under stress, maintenance, and capacity limits.',
                'sort_order' => 30,
            ],
            [
                'key' => 'ana_ruiz',
                'name' => 'Ana Ruiz',
                'title' => 'VP Finance',
                'perspective' => 'Capital allocation, balance-sheet discipline, incentives, and financial comparability.',
                'default_guidance' => 'Trace the cash, incentives, and accounting presentation separately; good economics can still create poor internal signals.',
                'sort_order' => 40,
            ],
            [
                'key' => 'kwame_osei',
                'name' => 'Kwame Osei',
                'title' => 'Political Risk',
                'perspective' => 'Government, partner, labor, and external stakeholder reaction risk.',
                'default_guidance' => 'Map the stakeholders who can slow execution, then decide which reactions are acceptable and which need active mitigation.',
                'sort_order' => 50,
            ],
            [
                'key' => 'margrethe_lund',
                'name' => 'Margrethe Lund',
                'title' => 'Board Member',
                'perspective' => 'Governance, accountability, long-term credibility, and board-level tradeoffs.',
                'default_guidance' => 'Make the recommendation defensible to a skeptical board: what principle are you applying, and what downside are you accepting?',
                'sort_order' => 60,
            ],
            [
                'key' => 'priya_venkatesan',
                'name' => 'Priya Venkatesan',
                'title' => 'Chief of Staff',
                'perspective' => 'Decision process, internal coordination, executive communication, and follow-through.',
                'default_guidance' => 'Clarify the decision owner, the message each function hears, and the follow-up actions needed so the choice does not fragment in execution.',
                'sort_order' => 70,
            ],
        ];
    }

    /**
     * @return Collection<int, Advisor>
     */
    public function ensureHaldenAdvisors(): Collection
    {
        $advisors = new Collection;

        foreach ($this->haldenAdvisors() as $advisor) {
            $advisors->push(Advisor::query()->firstOrCreate(
                ['key' => $advisor['key']],
                [
                    'name' => $advisor['name'],
                    'title' => $advisor['title'],
                    'perspective' => $advisor['perspective'],
                    'default_guidance' => $advisor['default_guidance'],
                    'content_version' => self::CONTENT_VERSION,
                    'sort_order' => $advisor['sort_order'],
                    'is_active' => true,
                    'metadata' => ['source' => 'Batch 7A advisor consultation framework'],
                ],
            ));
        }

        return $advisors;
    }
}
