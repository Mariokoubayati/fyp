<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLoanRequest;
use App\Http\Resources\Api\V1\LoanResource;
use App\Services\Lending\LoanService;
use Illuminate\Http\JsonResponse;

class LoanStoreController extends Controller
{
    /**
     * Ask the community for a loan.
     */
    public function __invoke(StoreLoanRequest $request, LoanService $loans): JsonResponse
    {
        $loan = $loans->request($request->user(), $request->validated());

        return (new LoanResource($loan->load(['borrower', 'lender'])))
            ->response()
            ->setStatusCode(201);
    }
}
