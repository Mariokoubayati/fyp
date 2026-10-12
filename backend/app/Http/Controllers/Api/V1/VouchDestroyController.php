<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VouchDestroyController extends Controller
{
    /**
     * Take a vouch back (idempotent).
     */
    public function __invoke(Request $request, User $user, TrustService $trust): Response
    {
        $voucher = $request->user();

        Vouch::where('voucher_id', $voucher->id)->where('vouchee_id', $user->id)->delete();
        $trust->forget($user, TrustEventType::VouchReceived, $voucher);

        return response()->noContent();
    }
}
