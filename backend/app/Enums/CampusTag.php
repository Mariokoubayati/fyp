<?php

namespace App\Enums;

/**
 * Topics shared by student interests, events and projects. Recommendations match on them.
 */
enum CampusTag: string
{
    case Academic = 'academic';
    case Tech = 'tech';
    case Sports = 'sports';
    case Social = 'social';
    case Culture = 'culture';
    case Arts = 'arts';
    case Career = 'career';
    case Volunteering = 'volunteering';
}
