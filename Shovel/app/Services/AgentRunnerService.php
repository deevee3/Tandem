<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Throwable;

class AgentRunnerService
{
    public const OUTPUT_SCHEMA = [
        'response' => 'string',
        'confidence' => 'numeric',
        'handoff' => 'boolean',
        'reason' => 'string',
        'policy_flags' => 'array',
    ];

    public function __construct(
        private readonly AgentPromptBuilder $promptBuilder,
        private readonly OpenAIClient $client,
    )
    {
    }

    public function run(Conversation $conversation): AgentRunResult
    {
        $payload = $this->buildPayload($conversation);

        try {
            $response = $this->client->responses($payload);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            return AgentRunResult::failure($exception->getMessage());
        } catch (Throwable $exception) {
            return AgentRunResult::failure($exception->getMessage());
        }

        $output = $this->extractOutput($response);

        if (! $this->validateSchema($output)) {
            return AgentRunResult::fallback('Malformed agent output.');
        }

        return AgentRunResult::success($this->normalizeOutput($output));
    }

    protected function buildPayload(Conversation $conversation): array
    {
        return [
            'model' => $this->model(),
            'messages' => $this->promptBuilder->build($conversation),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'agent_response',
                    'schema' => [
                        'type' => 'object',
                        'required' => array_keys(self::OUTPUT_SCHEMA),
                        'properties' => [
                            'response' => ['type' => 'string'],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'handoff' => ['type' => 'boolean'],
                            'reason' => ['type' => 'string'],
                            'policy_flags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                    'strict' => true,
                ],
            ],
        ];
    }

    protected function extractOutput(array $response): array
    {
        $content = Arr::get($response, 'data.choices.0.message.content');

        if (is_null($content)) {
            $content = Arr::get($response, 'choices.0.message.content');
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($content)) {
            return $content;
        }

        return [];
    }

    protected function validateSchema(array $output): bool
    {
        foreach (self::OUTPUT_SCHEMA as $key => $type) {
            if (! array_key_exists($key, $output)) {
                return false;
            }

            $value = $output[$key];

            if (! match ($type) {
                'string' => is_string($value),
                'numeric' => is_numeric($value),
                'boolean' => is_bool($value),
                'array' => is_array($value),
                default => false,
            }) {
                return false;
            }
        }

        $confidence = (float) $output['confidence'];

        return $confidence >= 0 && $confidence <= 1;
    }

    protected function normalizeOutput(array $output): array
    {
        $policyFlags = Arr::wrap($output['policy_flags']);

        return [
            'response' => (string) $output['response'],
            'confidence' => (float) $output['confidence'],
            'handoff' => (bool) $output['handoff'],
            'reason' => $this->normalizeReason($output['reason']),
            'policy_flags' => array_values(array_map(fn ($flag) => (string) $flag, $policyFlags)),
        ];
    }

    protected function normalizeReason(string $reason): string
    {
        $normalized = strtolower(trim($reason));

        return $normalized !== '' ? str_replace(' ', '_', $normalized) : 'uncertain_intent';
    }

    protected function model(): string
    {
        return Config::string('services.openai.model');
    }
}
