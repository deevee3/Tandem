<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueResource;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QueueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = Queue::query();

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
            'skills_required' => $validated['skills_required'] ?? null,
            'priority_policy' => $validated['priority_policy'] ?? null,
        ]);

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

        if (isset($validated['priority_policy'])) {
            $queue->priority_policy = $validated['priority_policy'];
        }

        $queue->save();

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

        $queue->delete();

        return response()->json([
            'message' => 'Queue deleted successfully',
        ], 200);
    }
}
