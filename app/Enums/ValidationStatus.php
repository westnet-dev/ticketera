<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
