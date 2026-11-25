<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CampusTag;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Ownership is enforced by EventPolicy in the controller.
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
        /** @var Event $event */
        $event = $this->route('event');
        $attendeeCount = $event->attendees()->count();
        $startsAt = $this->input('starts_at', $event->starts_at?->toIso8601String());

        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location' => ['sometimes', 'required', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'required', 'date', 'after:now', 'before:+1 year'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:'.$startsAt],
            'capacity' => ['sometimes', 'nullable', 'integer', 'between:'.max(2, $attendeeCount).',500'],
            'tags' => ['sometimes', 'required', 'array', 'min:1', 'max:3'],
            'tags.*' => ['string', 'distinct', Rule::enum(CampusTag::class)],
            'course_id' => ['sometimes', 'nullable', 'integer', Rule::exists('courses', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'capacity.between' => 'Capacity must be between :min and :max (it cannot drop below the number of students going).',
        ];
    }
}
