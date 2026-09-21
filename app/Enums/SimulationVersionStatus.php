<?php

namespace App\Enums;

enum SimulationVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
