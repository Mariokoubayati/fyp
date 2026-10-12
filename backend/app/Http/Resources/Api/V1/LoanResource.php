<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Loan;
use App\Services\Trust\TrustScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'purpose' => $this->purpose,
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status,
            'my_role' => $user ? $this->roleOf($user) : null,
            'borrower' => $this->whenLoaded('borrower', fn () => [
                'id' => $this->borrower->id,
                'name' => $this->borrower->name,
                'trust_score' => $this->borrower->trust_score,
                'trust_tier' => app(TrustScoreCalculator::class)->tierFor($this->borrower->trust_score)['name'],
            ]),
            'lender' => $this->whenLoaded('lender', fn () => $this->lender ? [
                'id' => $this->lender->id,
                'name' => $this->lender->name,
            ] : null),
            'handover' => [
                'lender_confirmed' => $this->lender_handover_at !== null,
                'borrower_confirmed' => $this->borrower_handover_at !== null,
            ],
            'repayment' => [
                'lender_confirmed' => $this->lender_repayment_at !== null,
                'borrower_confirmed' => $this->borrower_repayment_at !== null,
            ],
            'activated_at' => $this->activated_at,
            'repaid_at' => $this->repaid_at,
            'created_at' => $this->created_at,
        ];
    }
}
