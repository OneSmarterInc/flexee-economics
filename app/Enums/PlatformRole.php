<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Administrator = 'administrator';
    case Faculty = 'faculty';
    case Student = 'student';
}
