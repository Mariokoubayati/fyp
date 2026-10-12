<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
}
