<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CampusTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Any signed-in student with a verified email may post a project.
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
            'description' => ['required', 'string', 'max:2000'],
            'skills_needed' => ['required', 'array', 'min:1', 'max:8'],
            'skills_needed.*' => ['string', 'min:2', 'max:30'],
            'tags' => ['required', 'array', 'min:1', 'max:3'],
            'tags.*' => ['string', 'distinct', Rule::enum(CampusTag::class)],
            'team_size' => ['required', 'integer', 'between:2,10'],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
        ];
    }

    /**
     * Trim skills before validating them.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('skills_needed'))) {
            $this->merge([
                'skills_needed' => array_map(fn (mixed $skill): mixed => is_string($skill) ? trim($skill) : $skill, $this->input('skills_needed')),
            ]);
        }
    }

    /**
     * Validated data with duplicate skills removed (compared without case).
     *
     * @return array<string, mixed>
     */
    public function project(): array
    {
        $data = $this->validated();
        $data['skills_needed'] = array_values(collect($data['skills_needed'])->unique(fn (string $skill): string => mb_strtolower($skill))->all());

        return $data;
    }
}
