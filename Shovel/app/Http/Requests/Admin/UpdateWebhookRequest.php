<?php

namespace App\Http\Requests\Admin;

use App\Models\Webhook;
use App\Support\Authorization\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission(Permissions::WEBHOOKS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url', 'max:2048'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['string', 'max:255'],
            'active' => ['sometimes', 'required', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'rotate_secret' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>|null
     */
    public function sanitizedEvents(): ?array
    {
        if (! $this->providesEvents()) {
            return null;
        }

        $validated = $this->validated();
        $events = Arr::get($validated, 'events', []);
        $available = collect(Webhook::availableEvents());

        return collect($events)
            ->filter(static fn ($event) => is_string($event))
            ->map(static fn (string $event) => trim($event))
            ->filter(fn (string $event) => $event !== '' && $available->contains($event))
            ->unique()
            ->values()
            ->all();
    }

    public function providesEvents(): bool
    {
        return array_key_exists('events', $this->validated());
    }

    public function hasActiveFlag(): bool
    {
        return array_key_exists('active', $this->validated());
    }

    public function activeValue(): bool
    {
        return (bool) Arr::get($this->validated(), 'active');
    }

    public function providesMetadata(): bool
    {
        return array_key_exists('metadata', $this->validated());
    }

    public function metadata(): ?array
    {
        $validated = $this->validated();
        /** @var array|null $metadata */
        $metadata = Arr::get($validated, 'metadata');

        return $metadata;
    }

    public function wantsSecretRotation(): bool
    {
        return (bool) Arr::get($this->validated(), 'rotate_secret', false);
    }
}
