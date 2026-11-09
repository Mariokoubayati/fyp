<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BlockStoreController extends Controller
{
    /**
     * Block a student: neither side can message the other until it is lifted.
     */
    public function __invoke(Request $request, User $user): Response
    {
        abort_if($user->is($request->user()), 422, 'You cannot block yourself.');

        Block::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id]);

        return response()->noContent();
    }
}
