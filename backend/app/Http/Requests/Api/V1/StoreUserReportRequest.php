<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('user')->id !== $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer', Rule::exists('conversations', 'id')],
        ];
    }
}
