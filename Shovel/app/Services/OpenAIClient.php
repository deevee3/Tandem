<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAIClient
{
    public function chatCompletion(array $payload): array
    {
        try {
            Log::debug('Dispatching OpenAI chat completion request.', [
                'payload' => $payload,
            ]);
            return $this->request()->post('chat/completions', $payload)->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            throw $exception;
        }
    }

    protected function request(): PendingRequest
    {
        $apiKey = (string) Config::get('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Missing OpenAI API key configuration.');
        }

        $baseUrl = (string) Config::get('services.openai.base_url', 'https://api.openai.com/v1/');
        $timeout = (int) Config::get('services.openai.timeout', 30);

        return Http::baseUrl(rtrim($baseUrl, '/') . '/')
            ->timeout($timeout)
            ->withToken($apiKey)
            ->acceptJson();
    }
}
