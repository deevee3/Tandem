<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueResource;
use App\Models\Queue;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QueueController extends Controller
{
    protected AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = Queue::query()->with('users');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $paginator = $query->paginate($perPage);

        return QueueResource::collection($paginator)
            ->additional([
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function all(Request $request): JsonResponse
    {
        $queues = Queue::query()
            ->orderBy('name')
            ->get();

        return QueueResource::collection($queues)
            ->response()
            ->setStatusCode(200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:queues'],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'sla_first_response_minutes' => ['nullable', 'integer', 'min:1'],
            'sla_resolution_minutes' => ['nullable', 'integer', 'min:1'],
            'skills_required' => ['nullable', 'array'],
            'skills_required.*' => ['integer', 'exists:skills,id'],
            'priority_policy' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // If this queue is set as default, unset all other defaults
        if ($validated['is_default'] ?? false) {
            Queue::where('is_default', true)->update(['is_default' => false]);
        }

        $queue = Queue::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'sla_first_response_minutes' => $validated['sla_first_response_minutes'] ?? 15,
            'sla_resolution_minutes' => $validated['sla_resolution_minutes'] ?? 120,
            'skills_required' => $validated['skills_required'] ?? null,
            'priority_policy' => $validated['priority_policy'] ?? null,
        ]);

        $this->auditLog->logResourceAction('queue', 'created', $queue);

        return QueueResource::make($queue)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Queue $queue): JsonResponse
    {
        return QueueResource::make($queue)
            ->response()
            ->setStatusCode(200);
    }

    public function update(Request $request, Queue $queue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('queues')->ignore($queue->id)],
            'description' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'sla_first_response_minutes' => ['nullable', 'integer', 'min:1'],
            'sla_resolution_minutes' => ['nullable', 'integer', 'min:1'],
            'skills_required' => ['nullable', 'array'],
            'skills_required.*' => ['integer', 'exists:skills,id'],
            'priority_policy' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $original = $queue->getOriginal();

        // If this queue is set as default, unset all other defaults
        if (isset($validated['is_default']) && $validated['is_default']) {
            Queue::where('id', '!=', $queue->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        if (isset($validated['name'])) {
            $queue->name = $validated['name'];
        }

        if (isset($validated['slug'])) {
            $queue->slug = $validated['slug'];
        }

        if (isset($validated['description'])) {
            $queue->description = $validated['description'];
        }

        if (isset($validated['is_default'])) {
            $queue->is_default = $validated['is_default'];
        }

        if (isset($validated['skills_required'])) {
            $queue->skills_required = $validated['skills_required'];
        }

        if (isset($validated['sla_first_response_minutes'])) {
            $queue->sla_first_response_minutes = $validated['sla_first_response_minutes'];
        }

        if (isset($validated['sla_resolution_minutes'])) {
            $queue->sla_resolution_minutes = $validated['sla_resolution_minutes'];
        }

        if (isset($validated['priority_policy'])) {
            $queue->priority_policy = $validated['priority_policy'];
        }

        $queue->save();

        $this->auditLog->logResourceAction('queue', 'updated', $queue, [
            'changes' => $queue->getChanges(),
            'original' => [
                'name' => $original['name'] ?? null,
                'slug' => $original['slug'] ?? null,
                'description' => $original['description'] ?? null,
                'is_default' => $original['is_default'] ?? null,
                'sla_first_response_minutes' => $original['sla_first_response_minutes'] ?? null,
                'sla_resolution_minutes' => $original['sla_resolution_minutes'] ?? null,
            ],
        ]);

        return QueueResource::make($queue)
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Queue $queue): JsonResponse
    {
        // Prevent deletion of default queue
        if ($queue->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default queue',
            ], 422);
        }

        $this->auditLog->logResourceAction('queue', 'deleted', $queue);

        $queue->delete();

        return response()->json([
            'message' => 'Queue deleted successfully',
        ], 200);
    }

    public function assignUsers(Request $request, Queue $queue): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Sync users to the queue (replaces existing assignments)
        $queue->users()->sync($validated['user_ids']);

        return response()->json([
            'message' => 'Users assigned successfully',
            'queue' => QueueResource::make($queue->load('users')),
        ], 200);
    }

    public function unassignUser(Queue $queue, int $userId): JsonResponse
    {
        $queue->users()->detach($userId);

        return response()->json([
            'message' => 'User unassigned successfully',
        ], 200);
    }
}
