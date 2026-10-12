<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Trust\TrustScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrustScore
 */
class TrustResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'score' => $this->score,
            'tier' => $this->tier,
            'max_loan' => $this->maxLoan,
        ];
    }
}
