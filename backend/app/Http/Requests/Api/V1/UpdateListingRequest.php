<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ListingCategory;
use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    /**
     * Only the seller may change a listing; checked before validation runs.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    /**
     * Selling is a separate action, so status can only toggle between available and reserved here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999'],
            'category' => ['sometimes', 'required', Rule::enum(ListingCategory::class)],
            'condition' => ['sometimes', 'required', Rule::enum(ListingCondition::class)],
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:courses,id'],
            'status' => ['sometimes', 'required', Rule::in([ListingStatus::Available->value, ListingStatus::Reserved->value])],
        ];
    }
}
