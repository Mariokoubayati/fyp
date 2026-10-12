<?php

namespace App\Enums;

enum ListingCategory: string
{
    case Books = 'books';
    case Notes = 'notes';
    case Electronics = 'electronics';
    case Supplies = 'supplies';
    case Other = 'other';
}
