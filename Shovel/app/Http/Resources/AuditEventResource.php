<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AuditEvent */
class AuditEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'username' => $this->user->username,
                ];
            }),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->when($this->subject_type && $this->subject_id, function () {
                return [
                    'type' => $this->subject_type,
                    'id' => $this->subject_id,
                ];
            }),
            'payload' => $this->payload,
            'channel' => $this->channel,
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
