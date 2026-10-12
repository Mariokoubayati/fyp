<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use App\Services\Lending\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanConfirmRepaymentController extends Controller
{
    /**
     * Confirm that the money was paid back.
     */
    public function __invoke(Request $request, Loan $loan, LoanService $loans): LoanResource
    {
        Gate::authorize('confirm', $loan);

        return new LoanResource($loans->confirmRepayment($loan, $request->user())->load(['borrower', 'lender']));
    }
}
