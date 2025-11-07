<?php

use App\Models\Conversation;
use App\Services\AgentPromptBuilder;
use App\Services\AgentRunResult;
use App\Services\AgentRunnerService;
use App\Services\OpenAIClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

function makeAgentRunner(Mockery\MockInterface|OpenAIClient $client): AgentRunnerService
{
    $promptBuilder = app(AgentPromptBuilder::class);

    return new AgentRunnerService($promptBuilder, $client);
}

it('returns success when the OpenAI client yields valid agent output', function () {
    $conversation = Conversation::factory()->create();

    $client = Mockery::mock(OpenAIClient::class);
    $client->shouldReceive('chatCompletion')->once()->andReturn([
        'choices' => [[
            'message' => [
                'content' => json_encode([
                    'response' => 'Summary goes here.',
                    'confidence' => 0.88,
                    'handoff' => false,
                    'reason' => 'resolution',
                    'policy_flags' => [],
                ]),
            ],
        ]],
    ]);

    $service = makeAgentRunner($client);

    $result = $service->run($conversation);

    expect($result)->toBeInstanceOf(AgentRunResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->payload())->toMatchArray([
        'response' => 'Summary goes here.',
        'confidence' => 0.88,
        'handoff' => false,
        'reason' => 'resolution',
        'policy_flags' => [],
    ]);
});

it('falls back when the agent response violates the output schema', function () {
    $conversation = Conversation::factory()->create();

    $client = Mockery::mock(OpenAIClient::class);
    $client->shouldReceive('chatCompletion')->once()->andReturn([
        'choices' => [[
            'message' => [
                'content' => json_encode([
                    'confidence' => 0.2,
                    'handoff' => true,
                ]),
            ],
        ]],
    ]);

    $service = makeAgentRunner($client);

    $result = $service->run($conversation);

    expect($result->isFallback())->toBeTrue();
    expect($result->error())->toBe('Malformed agent output.');
});

it('returns failure when the OpenAI client throws an exception', function () {
    $conversation = Conversation::factory()->create();

    $client = Mockery::mock(OpenAIClient::class);
    $client->shouldReceive('chatCompletion')->once()->andThrow(new RuntimeException('Connection lost.'));

    $service = makeAgentRunner($client);

    $result = $service->run($conversation);

    expect($result->isFailure())->toBeTrue();
    expect($result->error())->toBe('Connection lost.');
});
