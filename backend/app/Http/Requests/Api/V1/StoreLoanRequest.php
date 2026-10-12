<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    /**
     * Any signed-in student with a verified email may ask for a loan.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The trust-tier cap is applied by LoanService, which knows the borrower's score.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'between:'.config('lending.min_amount').','.config('lending.max_amount')],
            'purpose' => ['nullable', 'string', 'max:200'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after:today', 'before_or_equal:'.now()->addDays(config('lending.max_days'))->toDateString()],
        ];
    }
}
