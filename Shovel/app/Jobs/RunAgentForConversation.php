<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Queue;
use App\Services\AgentRunnerService;
use App\Services\AgentRunResult;
use App\Services\PolicyEvaluationResult;
use App\Services\PolicyEvaluationService;
use App\Services\QueueResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SM\Factory\FactoryInterface;

class RunAgentForConversation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private ?QueueResolverService $queueResolver = null;

    public function __construct(
        private readonly int $conversationId,
    ) {
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle(
        AgentRunnerService $runner,
        FactoryInterface $stateMachineFactory,
        PolicyEvaluationService $policyEvaluationService,
        ?QueueResolverService $queueResolver = null,
    ): void
    {
        $this->queueResolver = $queueResolver;

        $conversation = Conversation::with('messages')->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $stateMachine = $stateMachineFactory->get($conversation, 'conversation');

        if ($conversation->status !== Conversation::STATUS_AGENT_WORKING && $stateMachine->can('agent_begins')) {
            $stateMachine->apply('agent_begins', false, [
                'channel' => 'system',
                'occurred_at' => now(),
            ]);

            $conversation->refresh();
            $stateMachine = $stateMachineFactory->get($conversation, 'conversation');
        }

        if ($conversation->status !== Conversation::STATUS_AGENT_WORKING) {
            return;
        }

        $result = $runner->run($conversation);

        if ($result->isFailure()) {
            throw new \RuntimeException($result->error() ?? 'Agent run failed.');
        }

        if ($result->isSuccess()) {
            $payload = $result->payload();
            $evaluation = $policyEvaluationService->evaluateAgentResponse($conversation, $payload);

            $this->persistAgentMessage($conversation, $payload, $evaluation);

            if ($evaluation->shouldHandoff()) {
                $this->triggerHandoff($conversation, $stateMachineFactory, $evaluation);
            }

            return;
        }

        if ($result->isFallback()) {
            $evaluation = $policyEvaluationService->evaluateAgentResponse($conversation, [
                'handoff' => true,
                'reason' => 'uncertain_intent',
                'confidence' => null,
                'policy_flags' => [],
                'handoff_metadata' => [
                    'error' => $result->error(),
                    'source' => 'agent_runner',
                ],
            ]);

            if (! $evaluation->shouldHandoff()) {
                $forcedReason = 'uncertain_intent';

                $evaluation = new PolicyEvaluationResult(
                    true,
                    $forcedReason,
                    $evaluation->confidence(),
                    $evaluation->policyHits(),
                    $evaluation->requiredSkills(),
                    $evaluation->handoffMetadata(),
                    array_merge($evaluation->queueMetadata(), ['reason' => $forcedReason])
                );
            }

            if ($evaluation->shouldHandoff()) {
                $this->triggerHandoff($conversation, $stateMachineFactory, $evaluation);
            }
        }
    }

    public function conversationId(): int
    {
        return $this->conversationId;
    }

    private function persistAgentMessage(Conversation $conversation, array $payload, PolicyEvaluationResult $evaluation): void
    {
        DB::transaction(function () use ($conversation, $payload, $evaluation) {
            $conversation->messages()->create([
                'sender_type' => Message::SENDER_AGENT,
                'content' => Arr::get($payload, 'response', ''),
                'confidence' => Arr::get($payload, 'confidence'),
                'metadata' => [
                    'reason' => Arr::get($payload, 'reason'),
                    'policy_flags' => Arr::get($payload, 'policy_flags'),
                    'source' => 'agent_runner',
                    'policy_evaluation' => $evaluation->handoffContext(),
                ],
            ]);
        });
    }

    private function triggerHandoff(Conversation $conversation, FactoryInterface $stateMachineFactory, PolicyEvaluationResult $evaluation): void
    {
        $stateMachine = $stateMachineFactory->get($conversation, 'conversation');
        $handoffTimestamp = now();

        if ($stateMachine->can('handoff_required')) {
            $stateMachine->apply('handoff_required', false, [
                'reason_code' => $evaluation->reason() ?? 'uncertain_intent',
                'confidence' => $evaluation->confidence(),
                'policy_hits' => $evaluation->policyHits(),
                'required_skills' => $evaluation->requiredSkills(),
                'handoff_metadata' => $evaluation->handoffMetadata(),
                'channel' => 'system',
                'occurred_at' => $handoffTimestamp,
                'handoff_at' => $handoffTimestamp,
            ]);

            $conversation->refresh();
            $stateMachine = $stateMachineFactory->get($conversation, 'conversation');
        }

        $queue = $this->resolveQueue($evaluation, $conversation);

        if (! $queue) {
            Log::warning('Unable to resolve queue for agent handoff.', [
                'conversation_id' => $conversation->id,
                'evaluation' => $evaluation->handoffContext(),
            ]);

            return;
        }

        if ($stateMachine->can('enqueue_for_human')) {
            $enqueueTimestamp = now();

            $stateMachine->apply('enqueue_for_human', false, [
                'queue_id' => $queue->id,
                'queue_item_metadata' => $evaluation->queueMetadata([
                    'policy_hits' => $evaluation->policyHits(),
                    'required_skills' => $evaluation->requiredSkills(),
                ]),
                'channel' => 'system',
                'occurred_at' => $enqueueTimestamp,
                'enqueued_at' => $enqueueTimestamp,
            ]);
        }
    }

    private function resolveQueue(PolicyEvaluationResult $evaluation, Conversation $conversation): ?Queue
    {
        if ($this->queueResolver === null) {
            return Queue::where('is_default', true)->first()
                ?? Queue::orderBy('id')->first();
        }

        return $this->queueResolver->resolveForEvaluation($evaluation, $conversation);
    }
}
