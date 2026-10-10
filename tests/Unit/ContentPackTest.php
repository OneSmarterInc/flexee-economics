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
