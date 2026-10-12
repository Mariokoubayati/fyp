<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use Illuminate\Support\Facades\Gate;

class LoanShowController extends Controller
{
    /**
     * Show one loan to its participants, or an open request to any student.
     */
    public function __invoke(Loan $loan): LoanResource
    {
        Gate::authorize('view', $loan);

        return new LoanResource($loan->load(['borrower', 'lender']));
    }
}
