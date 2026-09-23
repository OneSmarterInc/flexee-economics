<?php

namespace App\Enums;

enum KpiSnapshotStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
