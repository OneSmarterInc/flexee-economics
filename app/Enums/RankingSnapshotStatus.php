<?php

namespace App\Enums;

enum RankingSnapshotStatus: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
}
