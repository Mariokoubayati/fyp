<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CampusTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Any signed-in student with a verified email may post an event.
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
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date', 'after:now', 'before:+1 year'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'between:2,500'],
            'tags' => ['required', 'array', 'min:1', 'max:3'],
            'tags.*' => ['string', 'distinct', Rule::enum(CampusTag::class)],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
        ];
    }
}
