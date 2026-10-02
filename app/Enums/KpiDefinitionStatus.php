<?php

namespace App\Enums;

enum KpiDefinitionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
