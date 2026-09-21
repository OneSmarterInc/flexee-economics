<?php

namespace App\Enums;

enum SectionSimulationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';
}
