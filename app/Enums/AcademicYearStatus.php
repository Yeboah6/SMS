<?php

namespace App\Enums;

enum AcademicYearStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Locked = 'locked';
}
