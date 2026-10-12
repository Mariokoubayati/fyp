<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VouchStoreController extends Controller
{
    private const MAX_VOUCHES_GIVEN = 10;

    /**
     * Vouch for a classmate you know (idempotent). Restricted students cannot vouch.
     */
    public function __invoke(Request $request, User $user, TrustService $trust): Response
    {
        $voucher = $request->user();

        abort_if($voucher->is($user), 422, 'You cannot vouch for yourself.');
        abort_if($trust->current($voucher)->tier === 'restricted', 403, 'Your trust score is too low to vouch for others.');

        if (! Vouch::where('voucher_id', $voucher->id)->where('vouchee_id', $user->id)->exists()) {
            abort_if(Vouch::where('voucher_id', $voucher->id)->count() >= self::MAX_VOUCHES_GIVEN, 422, 'You have reached the maximum number of vouches.');

            $vouch = new Vouch;
            $vouch->voucher_id = $voucher->id;
            $vouch->vouchee_id = $user->id;
            $vouch->save();
        }

        $trust->record($user, TrustEventType::VouchReceived, $voucher);

        return response()->noContent();
    }
}
