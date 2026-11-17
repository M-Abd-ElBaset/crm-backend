<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
}