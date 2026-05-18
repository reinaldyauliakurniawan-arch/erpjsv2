<?php

namespace App\Enums;

enum ClassType: string
{
    case PRIVATE = 'private';
    case SEMI_PRIVATE = 'semi-private';
    case GROUP = 'group';
}
