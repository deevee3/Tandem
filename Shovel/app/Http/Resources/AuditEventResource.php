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
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'payload' => $this->payload, // Already includes actor, queue, assigned_user with roles/skills
            'channel' => $this->channel,
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
