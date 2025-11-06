<?php

namespace App\Http\Requests\Admin;

use App\Models\Webhook;
use App\Support\Authorization\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission(Permissions::WEBHOOKS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<string>
     */
    public function sanitizedEvents(): array
    {
        $events = Arr::get($this->validated(), 'events', []);
        $available = collect(Webhook::availableEvents());

        return collect($events)
            ->filter(static fn ($event) => is_string($event))
            ->map(static fn (string $event) => trim($event))
            ->filter(fn (string $event) => $event !== '' && $available->contains($event))
            ->unique()
            ->values()
            ->all();
    }

    public function shouldActivate(): bool
    {
        $active = Arr::get($this->validated(), 'active');

        return $active === null ? true : (bool) $active;
    }

    public function metadata(): ?array
    {
        /** @var array|null $metadata */
        $metadata = Arr::get($this->validated(), 'metadata');

        return $metadata;
    }
}
