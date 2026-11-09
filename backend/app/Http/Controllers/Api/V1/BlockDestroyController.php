<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BlockDestroyController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        Block::where(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id])->delete();

        return response()->noContent();
    }
}
