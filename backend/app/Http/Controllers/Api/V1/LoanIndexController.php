<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanIndexController extends Controller
{
    /**
     * List loans: ?role=borrowed, ?role=lent, or (default) open requests from other students.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $loans = Loan::query()
            ->with(['borrower', 'lender'])
            ->when(
                $request->string('role')->toString() === 'borrowed',
                fn ($query) => $query->where('borrower_id', $user->id)->latest(),
                fn ($query) => $query->when(
                    $request->string('role')->toString() === 'lent',
                    fn ($query) => $query->where('lender_id', $user->id)->latest(),
                    fn ($query) => $query
                        ->where('status', LoanStatus::Requested)
                        ->where('borrower_id', '!=', $user->id)
                        ->orderBy('due_date'),
                ),
            )
            ->paginate(20)
            ->withQueryString();

        return LoanResource::collection($loans);
    }
}
