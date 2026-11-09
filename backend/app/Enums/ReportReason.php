<?php

namespace App\Enums;

enum ReportReason: string
{
    case Harassment = 'harassment';
    case Spam = 'spam';
    case Scam = 'scam';
    case Inappropriate = 'inappropriate';
    case Other = 'other';
}
