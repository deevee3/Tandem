<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AppendHumanMessageRequest;
use App\Http\Requests\Api\AppendRequesterMessageRequest;
use App\Jobs\RunAgentForConversation;
use App\Models\Conversation;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SM\Factory\FactoryInterface;

class ConversationMessageController extends Controller
{
    public function __construct(
        private readonly FactoryInterface $stateMachineFactory,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function store(AppendRequesterMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = null;

        $conversation = DB::transaction(function () use ($conversation, $request, &$message) {
            $conversation->refresh();

            $message = $conversation->messages()->create($request->messageAttributes());

            return $conversation;
        });

        if ($message) {
            $this->auditLog->logMessage('requester_sent', $conversation->id, [
                'id' => $message->id,
                'content' => $message->content ?? '',
                'actor_type' => 'requester',
                'subject_type' => get_class($message),
                'subject_id' => $message->id,
            ]);
        }

        $conversation = $conversation->fresh(['messages']);

        $this->ensureAgentWorking($conversation);

        return response()->json([
            'data' => $conversation->toArray(),
        ], 201);
    }

    public function storeHumanMessage(AppendHumanMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $userId = auth()->id();
        $message = null;
        
        $conversation = DB::transaction(function () use ($conversation, $request, $userId, &$message) {
            $conversation->refresh();

            // Create message with user_id from authenticated user
            $message = $conversation->messages()->create($request->messageAttributes());
            
            // Update conversation's last_activity_at
            $conversation->last_activity_at = now();
            $conversation->save();

            return $conversation;
        });

        if ($message) {
            $this->auditLog->logMessage('human_sent', $conversation->id, [
                'id' => $message->id,
                'content' => $message->content ?? '',
                'actor_type' => 'user',
                'subject_type' => get_class($message),
                'subject_id' => $message->id,
            ]);
        }

        $conversation = $conversation->fresh(['messages.user', 'currentAssignment.user.roles', 'currentAssignment.user.skills']);

        return response()->json([
            'data' => $conversation->toArray(),
        ], 201);
    }

    protected function ensureAgentWorking(Conversation $conversation): void
    {
        $stateMachine = $this->stateMachineFactory->get($conversation, 'conversation');

        if ($stateMachine->can('agent_begins')) {
            $stateMachine->apply('agent_begins', false, [
                'channel' => 'api',
                'occurred_at' => now(),
            ]);

            RunAgentForConversation::dispatch($conversation->id);
        }
    }
}
