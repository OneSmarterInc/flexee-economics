<?php

namespace App\Enums;

enum SectionSimulationWeekStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Released = 'released';
    case Open = 'open';
    case Closed = 'closed';
    case Published = 'published';
}
