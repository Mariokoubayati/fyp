<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$this->user()->id])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['user_id.not_in' => 'You cannot message yourself.'];
    }
}
