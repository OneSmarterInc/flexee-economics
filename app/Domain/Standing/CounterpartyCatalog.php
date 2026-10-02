<?php

namespace App\Domain\Standing;

use App\Models\Counterparty;
use Illuminate\Support\Collection;

final class CounterpartyCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function haldenCounterparties(): array
    {
        return [
            ['key' => 'delacroix', 'name' => 'Delacroix', 'sort_order' => 10],
            ['key' => 'vestergaard', 'name' => 'Vestergaard', 'sort_order' => 20],
            ['key' => 'kuhn', 'name' => 'Kuhn', 'sort_order' => 30],
            ['key' => 'whitaker', 'name' => 'Whitaker', 'sort_order' => 40],
            ['key' => 'straits_pacific', 'name' => 'Straits Pacific', 'sort_order' => 50],
            ['key' => 'tetteh', 'name' => 'Tetteh', 'sort_order' => 60],
            ['key' => 'board', 'name' => 'Board', 'sort_order' => 70],
            ['key' => 'unions', 'name' => 'Unions', 'sort_order' => 80],
        ];
    }

    /**
     * @return Collection<int, Counterparty>
     */
    public function ensureHaldenCounterparties(): Collection
    {
        $counterparties = collect();

        foreach ($this->haldenCounterparties() as $counterparty) {
            $counterparties->push(Counterparty::query()->firstOrCreate(
                ['key' => $counterparty['key']],
                [
                    'name' => $counterparty['name'],
                    'sort_order' => $counterparty['sort_order'],
                    'is_active' => true,
                    'metadata' => ['source' => 'Batch 6A standing foundation'],
                ],
            ));
        }

        return $counterparties;
    }
}
