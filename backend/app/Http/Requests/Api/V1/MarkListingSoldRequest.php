<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkListingSoldRequest extends FormRequest
{
    /**
     * Only the seller may change a listing; checked before validation runs.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    /**
     * The buyer must be another student; recording them is what feeds the trust score later.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buyer_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([$this->user()->id])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buyer_id.not_in' => 'You cannot sell a listing to yourself.',
        ];
    }
}
