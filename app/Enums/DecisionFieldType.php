<?php

namespace App\Enums;

enum DecisionFieldType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Percentage = 'percentage';
    case Currency = 'currency';
    case Select = 'select';
    case Radio = 'radio';
    case Boolean = 'boolean';
    case ShortText = 'short_text';
}
