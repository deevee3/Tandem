<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClaimQueueItemRequest;
use App\Http\Requests\Api\ListQueueItemsRequest;
use App\Http\Resources\QueueItemResource;
use App\Models\Conversation;
use App\Models\Queue;
use App\Models\QueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SM\Factory\FactoryInterface;

class QueueItemController extends Controller
{
    public function __construct(private readonly FactoryInterface $stateMachineFactory)
    {
    }

    public function index(ListQueueItemsRequest $request, Queue $queue): JsonResponse
    {
        $validated = $request->validated();

        $state = $validated['state'] ?? null;

        $query = $queue->items()
            ->with([
                'queue.users',
                'conversation' => function ($conversationQuery) {
                    $conversationQuery->with([
                        'messages' => function ($messageQuery) {
                            $messageQuery->latest()->limit(1);
                        },
                        'currentAssignment.user.roles',
                        'currentAssignment.user.skills',
                    ]);
                },
            ])
            ->orderByDesc('enqueued_at');

        if ($state) {
            $query->where('state', $state);
        }

        $paginator = $query->paginate($validated['per_page'] ?? 50);

        return QueueItemResource::collection($paginator)
            ->additional([
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => [
                        'state' => $state,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function claim(ClaimQueueItemRequest $request, Queue $queue, QueueItem $queueItem): JsonResponse
    {
        if ($queueItem->queue_id !== $queue->id) {
            abort(404);
        }

        if ($queueItem->state !== QueueItem::STATE_QUEUED) {
            abort(409, 'Queue item cannot be claimed in its current state.');
        }

        $claimedItem = DB::transaction(function () use ($queueItem, $queue, $request) {
            /** @var QueueItem $lockedQueueItem */
            $lockedQueueItem = QueueItem::query()->lockForUpdate()->findOrFail($queueItem->id);

            if ($lockedQueueItem->state !== QueueItem::STATE_QUEUED) {
                abort(409, 'Queue item cannot be claimed in its current state.');
            }

            /** @var Conversation $conversation */
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($lockedQueueItem->conversation_id);

            $stateMachine = $this->stateMachineFactory->get($conversation, 'conversation');

            if (! $stateMachine->can('assign_human')) {
                abort(409, 'Conversation is not ready to be assigned to a human.');
            }

            $stateMachine->apply('assign_human', false, [
                'actor_id' => $request->actorId(),
                'queue_id' => $queue->id,
                'assignment_user_id' => $request->assignmentUserId(),
                'assignment_metadata' => $request->assignmentMetadata(),
                'assigned_at' => now(),
                'channel' => 'api',
            ]);

            return $lockedQueueItem->fresh([
                'conversation' => fn ($conversationQuery) => $conversationQuery->with([
                    'messages' => fn ($messageQuery) => $messageQuery->latest()->limit(1),
                ]),
            ]);
        });

        return QueueItemResource::make($claimedItem)
            ->response()
            ->setStatusCode(200);
    }
}
