<?php

namespace Tests\Unit;

use App\Halden\Ai\ReplyCheck;
use PHPUnit\Framework\TestCase;

class ReplyCheckTest extends TestCase
{
    private const OK = 'Texas rigs cost money every quarter, and the extra oil from each one gets smaller. Look at what the last rig adds before you keep it.';

    public function test_dollar_figures_are_read_the_way_people_write_them(): void
    {
        $f = ReplyCheck::dollarFigures('We made $3,750M, or $3.75 billion. A rig is $40 million; tea is $2.50; fees were $120K; debt -$200M.');
        $this->assertSame([3.75e9, 3.75e9, 4e7, 2.5, 1.2e5, 2e8], array_values($f));
    }

    public function test_a_clean_reply_passes(): void
    {
        $this->assertNull(ReplyCheck::failure(self::OK.' A rig costs $40M.', ['A rig costs $40 million a quarter.'], [], 15, 230));
    }

    public function test_an_invented_figure_is_caught(): void
    {
        $reason = ReplyCheck::failure(self::OK.' The last rig earns about $37 million.', ['A rig costs $40 million a quarter.'], [], 15, 230);
        $this->assertSame('Dollar figure not in its inputs: $37 million.', $reason);
    }

    public function test_rounding_a_figure_is_allowed(): void
    {
        $this->assertNull(ReplyCheck::failure(self::OK.' Oil is about $71 now.', ['WTI fell to $71.00 a barrel.'], [], 15, 230));
        $this->assertNull(ReplyCheck::failure(self::OK.' Roughly $3.7 billion.', ['earned $3,750M'], [], 15, 230));
    }

    public function test_banned_words_are_whole_words_and_the_teams_own_words_are_allowed(): void
    {
        $this->assertSame('Banned word: "simulation".', ReplyCheck::failure(self::OK.' This simulation rewards it.', [], [], 15, 230));
        $this->assertNull(ReplyCheck::failure(self::OK.' There is room for doubt.', [], ['Is there room to cut rigs?'], 15, 230));
        $this->assertNull(ReplyCheck::failure(self::OK.' It is a classic mistake to keep them all.', [], [], 15, 230));
    }

    public function test_length_and_lists_are_checked(): void
    {
        $this->assertStringStartsWith('Length:', (string) ReplyCheck::failure('Too short.', [], [], 15, 230));
        $this->assertStringStartsWith('Formatting:', (string) ReplyCheck::failure(self::OK."\n- first point\n- second point", [], [], 15, 230));
    }
}
