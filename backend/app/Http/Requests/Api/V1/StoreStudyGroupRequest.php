<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudyGroupRequest extends FormRequest
{
    /**
     * Any signed-in student with a verified email may start a group.
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
            'course_id' => [
                'required',
                'integer',
                Rule::exists('course_user', 'course_id')->where('user_id', $this->user()->id),
            ],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date', 'after:now'],
            'capacity' => ['sometimes', 'integer', 'between:2,30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_id.exists' => 'You can only start a group for a course you are enrolled in.',
        ];
    }
}
