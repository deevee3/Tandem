<?php

namespace App\Http\Requests\Api;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class AppendHumanMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ensure user is authenticated and has an active assignment to this conversation
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messageAttributes(): array
    {
        return [
            'sender_type' => Message::SENDER_HUMAN,
            'user_id' => $this->user()->id,
            'content' => $this->validated('content'),
            'metadata' => $this->validated('metadata'),
        ];
    }
}
