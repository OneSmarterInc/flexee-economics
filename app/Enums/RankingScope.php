<?php

namespace App\Enums;

enum RankingScope: string
{
    case WithinSection = 'within_section';
    case CrossSection = 'cross_section';
}
