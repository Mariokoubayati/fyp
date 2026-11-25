<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CampusTag;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Ownership is enforced by ProjectPolicy in the controller.
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
        /** @var Project $project */
        $project = $this->route('project');
        $memberCount = $project->members()->count();

        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'required', 'string', 'max:2000'],
            'skills_needed' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
            'skills_needed.*' => ['string', 'min:2', 'max:30'],
            'tags' => ['sometimes', 'required', 'array', 'min:1', 'max:3'],
            'tags.*' => ['string', 'distinct', Rule::enum(CampusTag::class)],
            'team_size' => ['sometimes', 'required', 'integer', 'between:'.max(2, $memberCount).',10'],
            'is_open' => ['sometimes', 'boolean'],
            'course_id' => ['sometimes', 'nullable', 'integer', Rule::exists('courses', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_size.between' => 'Team size must be between :min and :max (it cannot drop below the current team).',
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

        if (array_key_exists('skills_needed', $data)) {
            $data['skills_needed'] = array_values(collect($data['skills_needed'])->unique(fn (string $skill): string => mb_strtolower($skill))->all());
        }

        return $data;
    }
}
