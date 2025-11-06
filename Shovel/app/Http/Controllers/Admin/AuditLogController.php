<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'event_type' => ['nullable', 'string'],
            'occurred_from' => ['nullable', 'date'],
            'occurred_to' => ['nullable', 'date'],
            'actor' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->filled('occurred_from') && $request->filled('occurred_to')) {
                $from = Carbon::parse($request->input('occurred_from'));
                $to = Carbon::parse($request->input('occurred_to'));

                if ($from->greaterThan($to)) {
                    $validator->errors()->add('occurred_from', __('The occurred_from must be before occurred_to.'));
                }
            }
        });

        $validated = $validator->validate();

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = AuditEvent::query()
            ->with('user:id,name,email,username')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (array_key_exists('conversation_id', $validated) && $validated['conversation_id']) {
            $query->where('conversation_id', $validated['conversation_id']);
        }

        if (! empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        if (! empty($validated['occurred_from'])) {
            $query->where('occurred_at', '>=', Carbon::parse($validated['occurred_from'])->startOfDay());
        }

        if (! empty($validated['occurred_to'])) {
            $query->where('occurred_at', '<=', Carbon::parse($validated['occurred_to'])->endOfDay());
        }

        if (! empty($validated['actor'])) {
            $actor = $validated['actor'];
            $escapedActor = addcslashes($actor, '%_');
            $like = "%{$escapedActor}%";

            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->whereHas('user', function ($userQuery) use ($like) {
                    $userQuery->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });

                $driver = DB::connection()->getDriverName();

                if ($driver === 'sqlite') {
                    $searchQuery->orWhereRaw('JSON_EXTRACT(payload, ?) LIKE ?', ['$.actor.name', $like])
                        ->orWhereRaw('JSON_EXTRACT(payload, ?) LIKE ?', ['$.actor.email', $like])
                        ->orWhereRaw('JSON_EXTRACT(payload, ?) LIKE ?', ['$.actor.username', $like]);
                } else {
                    $searchQuery->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) LIKE ?', ['$.actor.name', $like])
                        ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) LIKE ?', ['$.actor.email', $like])
                        ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) LIKE ?', ['$.actor.username', $like]);
                }
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return AuditEventResource::collection($paginator)
            ->additional([
                'meta' => [
                    'filters' => [
                        'conversation_id' => isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
                        'event_type' => $validated['event_type'] ?? null,
                        'occurred_from' => $validated['occurred_from'] ?? null,
                        'occurred_to' => $validated['occurred_to'] ?? null,
                        'actor' => (string) ($validated['actor'] ?? null),
                    ],
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
            ])
            ->response();
    }
}
