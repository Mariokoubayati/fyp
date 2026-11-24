<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReportReason;
use App\Models\Conversation;
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
            'conversation_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $conversation = Conversation::find($value);

                    if (! $conversation || ! $conversation->includes($this->user()) || ! $conversation->includes($this->route('user'))) {
                        $fail('You can only attach a conversation between you and this student.');
                    }
                },
            ],
        ];
    }
}
