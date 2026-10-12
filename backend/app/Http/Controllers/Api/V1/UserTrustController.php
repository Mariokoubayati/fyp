<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrustResource;
use App\Models\User;
use App\Services\Trust\TrustService;

class UserTrustController extends Controller
{
    /**
     * A student's public trust summary: score, tier and the largest loan that tier allows.
     */
    public function __invoke(User $user, TrustService $trust): TrustResource
    {
        return new TrustResource($trust->current($user));
    }
}
