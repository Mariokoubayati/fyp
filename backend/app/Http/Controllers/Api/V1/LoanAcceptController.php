<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use App\Services\Lending\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanAcceptController extends Controller
{
    /**
     * Agree to lend to the borrower.
     */
    public function __invoke(Request $request, Loan $loan, LoanService $loans): LoanResource
    {
        Gate::authorize('accept', $loan);

        return new LoanResource($loans->accept($loan, $request->user())->load(['borrower', 'lender']));
    }
}
