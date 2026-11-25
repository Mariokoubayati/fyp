<?php

namespace App\Enums;

/**
 * Where a student stands with a project: asked to join, on the team, or turned down.
 */
enum ProjectMemberStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
