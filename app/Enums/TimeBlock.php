<?php

namespace App\Enums;

enum TimeBlock: string
{
    case T0900 = '09.00-10.30';
    case T1030 = '10.30-12.00';
    case T1300 = '13.00-14.30';
    case T1430 = '14.30-16.00';
    case T1600 = '16.00-17.30';
    case T1830 = '18.30-20.00';
    case CUSTOM = 'Custom';
}
