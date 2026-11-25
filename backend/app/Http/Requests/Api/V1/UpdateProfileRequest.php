<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CampusTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'interests' => ['sometimes', 'array', 'max:'.count(CampusTag::cases())],
            'interests.*' => ['string', Rule::enum(CampusTag::class)],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'min:2', 'max:30'],
        ];
    }

    /**
     * Trim skills before validating them.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('skills'))) {
            $this->merge([
                'skills' => array_map(fn (mixed $skill): mixed => is_string($skill) ? trim($skill) : $skill, $this->input('skills')),
            ]);
        }
    }

    /**
     * Validated data with duplicate interests and skills removed (skills compared without case).
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $data = $this->validated();

        if (array_key_exists('interests', $data)) {
            $data['interests'] = array_values(array_unique($data['interests']));
        }

        if (array_key_exists('skills', $data)) {
            $data['skills'] = array_values(collect($data['skills'])->unique(fn (string $skill): string => mb_strtolower($skill))->all());
        }

        return $data;
    }
}
