<?php

namespace Tests\Unit;

use App\Halden\Content\ContentPack;
use PHPUnit\Framework\TestCase;

/**
 * The results story is picked by what the team did and filled with its own numbers, including the
 * sentences a quarter picks by one choice ("fills"), which carry placeholders of their own.
 */
class ContentPackTest extends TestCase
{
    private function pack(): ContentPack
    {
        return new ContentPack(dirname(__DIR__, 2).'/packages/content');
    }

    public function test_quarter_eight_story_names_the_outcome_and_fills_the_crude_bought_ahead(): void
    {
        $results = ['ops.wti_shock' => 14.0, 'ops.wti' => 88.0, 'ops.gc' => 17.1, 'money.ebitda' => 4339.2,
            'line.crude_bought_ahead' => 209.66, 'line.inventory_carry' => -23.5];
        $story = $this->pack()->story(8, $results, ['rigs' => 14, 'opec_case' => 'full'], []);
        $this->assertSame('full', $story['band']);
        $this->assertSame('The cut held', $story['title']);
        $text = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Oil jumped to $88.', $text);
        $this->assertStringContainsString('$17.10 a barrel', $text);
        $this->assertStringContainsString('bought 30 days', $text);
        $this->assertStringContainsString('came out at $210M, after $24M of interest', $text);
        $this->assertStringNotContainsString('{', $text, 'every placeholder is filled, including those inside a picked sentence');

        $story = $this->pack()->story(8, ['ops.wti_shock' => -4.0] + $results, ['rigs' => 14, 'opec_case' => 'fails'], []);
        $this->assertSame('fails', $story['band']);
        $this->assertStringContainsString('bought nothing ahead', implode(' ', $story['paragraphs']));
    }

    public function test_quarter_nine_story_is_picked_by_the_rebrand_and_names_the_price_war(): void
    {
        $results = ['ops.rebrand_outlay' => 193.72, 'line.cordell_shop' => 165.0, 'ops.nonfuel_per_gal' => 0.38, 'money.ebitda' => 4000.0];
        $d = ['rigs' => 14, 'rebrand_core' => 'keep', 'rebrand_gulf' => 'rebrand', 'rebrand_edge' => 'rebrand'];
        $story = $this->pack()->story(9, $results, $d, [], ['{rebranded_regions}' => 'Gulf Coast beyond the core and Southeast edge']);
        $this->assertSame('partial', $story['band']);
        $text = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Halden name on Gulf Coast beyond the core and Southeast edge', $text);
        $this->assertStringContainsString('cost of $194M', $text);
        $this->assertStringContainsString('shop margin is 38 cents a gallon this year instead of 42 cents', $text);
        $this->assertStringNotContainsString('{', $text);
        $this->assertSame('none', $this->pack()->story(9, $results, ['rigs' => 14], [])['band']);
        $calm = $this->pack()->story(9, ['ops.nonfuel_per_gal' => 0.45] + $results, ['rebrand_core' => 'rebrand'] + $d, [], ['{rebranded_regions}' => 'all three']);
        $this->assertSame('full', $calm['band']);
        $this->assertStringContainsString('better than the usual 42 cents', implode(' ', $calm['paragraphs']));
    }

    public function test_quarter_eleven_story_is_picked_by_the_kessana_answer(): void
    {
        $results = ['ops.kessana_take' => 0.68, 'line.kessana_take_change' => -50.1, 'ops.kessana_forgone' => 0.0, 'money.ebitda' => 3244.3];
        $story = $this->pack()->story(11, $results, ['rigs' => 14, 'kessana_position' => 'counter'], []);
        $this->assertSame('counter', $story['band']);
        $this->assertSame('The middle', $story['title']);
        $text = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('costs Halden $50M this quarter', $text);
        $this->assertStringNotContainsString('{', $text);
        $this->assertSame('accept', $this->pack()->story(11, $results, ['rigs' => 14], [])['band'], 'an unanswered team signed');
        $gone = $this->pack()->story(11, ['ops.kessana_forgone' => 317.3, 'line.kessana_take_change' => 0.0] + $results, ['rigs' => 14, 'kessana_position' => 'exit'], []);
        $this->assertSame('Out of Kessana', $gone['title']);
        $this->assertStringContainsString('$317M this quarter alone', implode(' ', $gone['paragraphs']));
        $this->assertSame('The bluff', $this->pack()->story(11, $results, ['rigs' => 14, 'kessana_position' => 'threaten'], [])['title']);
        $who = array_column($this->pack()->relations(11, $results, ['kessana_position' => 'threaten'], [], null), 'who');
        $this->assertSame(['Minister Tetteh', 'Grant Whitaker'], $who);
    }

    public function test_quarter_twelve_story_is_picked_by_the_shape_of_the_portfolio(): void
    {
        $results = ['ops.divest_proceeds' => 550.0, 'ops.carbon' => 40.0, 'money.ebitda' => 3300.0];
        $d = ['rigs' => 14, 'port_helix_rotterdam' => 'go', 'port_offshore_wind' => 'go', 'port_euro_retail_divest' => 'go'];
        $story = $this->pack()->story(12, $results, $d, [], ['{portfolio_list}' => 'Helix at Rotterdam and offshore wind', '{portfolio_cost}' => '$1,750M']);
        $this->assertSame('transition', $story['band']);
        $text = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Helix at Rotterdam and offshore wind, $1,750M over five years', $text);
        $this->assertStringContainsString('$550M came in and paid down debt', $text);
        $this->assertStringNotContainsString('{', $text);
        $this->assertSame('oil', $this->pack()->story(12, $results, ['rigs' => 14, 'port_permian_expansion' => 'go'], [], ['{portfolio_list}' => 'x', '{portfolio_cost}' => '$650M'])['band']);
        $this->assertSame('both', $this->pack()->story(12, $results, ['rigs' => 14, 'port_permian_expansion' => 'go', 'port_offshore_wind' => 'go'], [], ['{portfolio_list}' => 'x', '{portfolio_cost}' => '$1,200M'])['band']);
        $none = $this->pack()->story(12, ['ops.divest_proceeds' => 0.0] + $results, ['rigs' => 14], [], ['{portfolio_list}' => 'nothing', '{portfolio_cost}' => '$0M']);
        $this->assertSame('none', $none['band']);
        $this->assertStringContainsString('You kept the European stations.', implode(' ', $none['paragraphs']));
    }

    public function test_quarter_seven_story_is_picked_by_how_many_markets_were_matched(): void
    {
        $results = ['ops.rival_match_cost' => 46.1, 'ops.rival_ignore_cost' => 0.07, 'money.ebitda' => 3862.0];
        $d = ['rigs' => 14, 'resp_urban' => 'match', 'resp_suburban' => 'match', 'capacity_response' => 'hold'];
        $story = $this->pack()->story(7, $results, $d, []);
        $this->assertSame('mixed', $story['band']);
        $this->assertStringContainsString('built nothing', implode(' ', $story['paragraphs']));
        $this->assertSame('held', $this->pack()->story(7, $results, ['rigs' => 14], [])['band']);
        $this->assertSame('matched', $this->pack()->story(7, $results, $d + ['resp_rural' => 'match'], [])['band']);
    }
}
