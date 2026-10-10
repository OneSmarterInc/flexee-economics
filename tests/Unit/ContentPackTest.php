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
