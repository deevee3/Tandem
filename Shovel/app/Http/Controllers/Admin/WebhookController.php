<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebhookRequest;
use App\Http\Requests\Admin\UpdateWebhookRequest;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebhookController extends Controller
{
    protected AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));
        $search = trim((string) $request->input('search', ''));
        $active = $request->has('active') ? $request->boolean('active') : null;
        $event = trim((string) $request->input('event', ''));

        $query = Webhook::query()
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if ($active !== null) {
            $query->where('active', $active);
        }

        if ($event !== '') {
            $query->whereJsonContains('events', $event);
        }

        $paginator = $query->paginate($perPage);

        return WebhookResource::collection($paginator)
            ->additional([
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search !== '' ? $search : null,
                        'active' => $request->has('active') ? $active : null,
                        'event' => $event !== '' ? $event : null,
                    ],
                    'available_events' => Webhook::availableEvents(),
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $secret = $this->generateSecret();
        $events = $request->sanitizedEvents();

        $this->ensureValidEvents($events);

        $validated = $request->validated();

        $payload = [
            'name' => Arr::get($validated, 'name'),
            'url' => Arr::get($validated, 'url'),
            'events' => $events,
            'secret' => Crypt::encryptString($secret),
            'active' => $request->shouldActivate(),
            'metadata' => $request->metadata(),
        ];

        $webhook = DB::transaction(function () use ($payload) {
            return Webhook::query()->create($payload);
        });

        $this->auditLog->logResourceAction('webhook', 'created', $webhook, [
            'events' => $webhook->events ?? [],
        ]);

        return WebhookResource::make($webhook)
            ->additional([
                'plain_text_secret' => $secret,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Webhook $webhook): JsonResponse
    {
        return WebhookResource::make($webhook)
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $attributes = [];
        $plainSecret = null;

        $original = $webhook->getOriginal();
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $attributes['name'] = Arr::get($validated, 'name');
        }

        if (array_key_exists('url', $validated)) {
            $attributes['url'] = Arr::get($validated, 'url');
        }

        if ($request->providesEvents()) {
            $events = $request->sanitizedEvents();
            $this->ensureValidEvents($events);
            $attributes['events'] = $events;
        }

        if ($request->providesMetadata()) {
            $attributes['metadata'] = $request->metadata();
        }

        if ($request->hasActiveFlag()) {
            $attributes['active'] = $request->activeValue();
        }

        if ($request->wantsSecretRotation()) {
            $plainSecret = $this->generateSecret();
            $attributes['secret'] = Crypt::encryptString($plainSecret);
        }

        if ($attributes !== []) {
            $webhook->fill($attributes)->save();
        }

        $this->auditLog->logResourceAction('webhook', 'updated', $webhook, [
            'changes' => $webhook->getChanges(),
            'original' => [
                'name' => $original['name'] ?? null,
                'url' => $original['url'] ?? null,
                'active' => $original['active'] ?? null,
            ],
            'secret_rotated' => $plainSecret !== null,
        ]);

        return WebhookResource::make($webhook->fresh())
            ->additional([
                'plain_text_secret' => $plainSecret,
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->auditLog->logResourceAction('webhook', 'deleted', $webhook);

        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully.',
        ], 200);
    }

    public function test(Request $request, Webhook $webhook): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ]);

        $event = trim((string) Arr::get($validated, 'event'));

        if ($event === '') {
            throw ValidationException::withMessages([
                'event' => 'A valid event is required.',
            ]);
        }

        $availableEvents = collect(Webhook::availableEvents());

        if ($availableEvents->isNotEmpty() && ! $availableEvents->contains($event)) {
            throw ValidationException::withMessages([
                'event' => 'Select a valid event to test.',
            ]);
        }

        try {
            $secret = Crypt::decryptString($webhook->getAttribute('secret'));
        } catch (Throwable) {
            return response()->json([
                'status' => null,
                'successful' => false,
                'error' => 'Unable to decrypt the webhook secret. Rotate the secret and try again.',
            ], 422);
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => Arr::get($validated, 'payload', []),
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
            ],
        ];

        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            throw ValidationException::withMessages([
                'payload' => 'Unable to encode payload as JSON.',
            ]);
        }

        $signature = 'sha256=' . hash_hmac('sha256', $encodedPayload, $secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Test' => 'true',
                ])
                ->post($webhook->url, $payload);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => null,
                'successful' => false,
                'error' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $this->decodeResponseBody($response),
            'headers' => $response->headers(),
            'request_payload' => $payload,
        ], 200);
    }

    /**
     * @param  list<string>  $events
     */
    protected function ensureValidEvents(array $events): void
    {
        if ($events === []) {
            throw ValidationException::withMessages([
                'events' => 'At least one valid event must be selected.',
            ]);
        }
    }

    protected function generateSecret(): string
    {
        return 'whsk_' . Str::random(48);
    }

    protected function decodeResponseBody(Response $response): mixed
    {
        $contentType = strtolower($response->header('Content-Type') ?? '');

        if ($contentType !== '' && str_contains($contentType, 'json')) {
            $decoded = json_decode($response->body(), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $response->body();
    }
}
