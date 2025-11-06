<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'avatar' => $this->avatar,
            'email_verified_at' => optional($this->email_verified_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'code' => $role->code,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'hourly_rate' => $role->hourly_rate ? (float) $role->hourly_rate : null,
                    ];
                });
            }),
            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'code' => $skill->code,
                        'name' => $skill->name,
                        'slug' => $skill->slug,
                        'level' => $skill->pivot->level ?? null,
                        'hourly_rate' => $skill->hourly_rate ? (float) $skill->hourly_rate : null,
                    ];
                });
            }),
        ];

        // Calculate total hourly rate when roles and skills are loaded
        if ($this->relationLoaded('roles') && $this->relationLoaded('skills')) {
            $roleHourlyRate = $this->roles->sum(fn($role) => $role->hourly_rate ?? 0);
            $skillHourlyRate = $this->skills->sum(fn($skill) => $skill->hourly_rate ?? 0);
            $data['total_hourly_rate'] = round($roleHourlyRate + $skillHourlyRate, 2);
        }

        return $data;
    }
}
