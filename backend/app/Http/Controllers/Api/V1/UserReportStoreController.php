<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserReportRequest;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Http\Response;

class UserReportStoreController extends Controller
{
    /**
     * Report a student. One report per pair; reporting again updates it.
     */
    public function __invoke(StoreUserReportRequest $request, User $user): Response
    {
        UserReport::updateOrCreate(
            ['reporter_id' => $request->user()->id, 'reported_id' => $user->id],
            $request->safe()->only(['reason', 'details', 'conversation_id']),
        );

        return response()->noContent();
    }
}
