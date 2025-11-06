<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\HandoffPolicy */
class HandoffPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reason_code' => $this->reason_code,
            'confidence_threshold' => $this->confidence_threshold !== null
                ? (float) $this->confidence_threshold
                : null,
            'metadata' => $this->metadata,
            'active' => (bool) $this->active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                    ];
                });
            }),
            'rules' => $this->whenLoaded('rules', function () {
                return HandoffPolicyRuleResource::collection($this->rules);
            }),
        ];
    }
}
