<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use App\Services\Lending\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanCancelController extends Controller
{
    /**
     * Withdraw a request, or drop an accepted loan before the money moves.
     */
    public function __invoke(Request $request, Loan $loan, LoanService $loans): LoanResource
    {
        Gate::authorize('cancel', $loan);

        return new LoanResource($loans->cancel($loan, $request->user())->load(['borrower', 'lender']));
    }
}
