<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * The route is behind auth:api and only ever edits the caller's own profile.
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'major' => ['sometimes', 'nullable', 'string', 'max:100'],
            'study_year' => ['sometimes', 'nullable', 'integer', 'between:1,6'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
