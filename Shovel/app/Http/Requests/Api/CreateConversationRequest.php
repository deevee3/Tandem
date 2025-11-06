<?php

namespace App\Http\Requests\Api;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'requester.type' => ['nullable', 'string', 'max:50'],
            'requester.identifier' => ['nullable', 'string', 'max:255'],
            'case_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'initial_message.content' => ['nullable', 'string'],
            'initial_message.metadata' => ['nullable', 'array'],
        ];
    }

    public function conversationAttributes(): array
    {
        return [
            'subject' => $this->validated('subject'),
            'priority' => $this->validated('priority', 'standard'),
            'requester_type' => $this->validated('requester.type'),
            'requester_identifier' => $this->validated('requester.identifier'),
            'case_id' => $this->validated('case_id'),
            'metadata' => $this->validated('metadata'),
        ];
    }

    public function initialMessageAttributes(): ?array
    {
        $content = $this->validated('initial_message.content');

        if ($content === null) {
            return null;
        }

        return [
            'sender_type' => Message::SENDER_REQUESTER,
            'content' => $content,
            'metadata' => $this->validated('initial_message.metadata'),
        ];
    }
}
