<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudyGroupRequest extends FormRequest
{
    /**
     * Ownership is enforced by StudyGroupPolicy in the controller.
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
        $memberCount = $this->route('study_group')->members()->count();

        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'required', 'date', 'after:now'],
            'capacity' => ['sometimes', 'integer', 'between:'.max(2, $memberCount).',30'],
        ];
    }
}
