<?php

namespace App\Enums;

enum StandingValue: string
{
    case Cooperative = 'cooperative';
    case Guarded = 'guarded';
    case Strained = 'strained';
    case Watchful = 'watchful';
    case Obliged = 'obliged';
    case Hostile = 'hostile';
}
