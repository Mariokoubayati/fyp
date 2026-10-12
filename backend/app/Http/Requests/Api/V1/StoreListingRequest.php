<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ListingCategory;
use App\Enums\ListingCondition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    /**
     * Any signed-in student with a verified email may sell.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'category' => ['required', Rule::enum(ListingCategory::class)],
            'condition' => ['required', Rule::enum(ListingCondition::class)],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ];
    }
}
