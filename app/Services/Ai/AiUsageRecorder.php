<?php

namespace App\Services\Ai;

use App\Enums\AiUsageFeature;
use App\Models\AiUsageLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists token usage and estimated cost for each Gemini response so the
 * admin analytics dashboard can report on it. Never interrupts the request.
 */
class AiUsageRecorder
{
    public function record(Response $response, string $model, AiUsageFeature $feature, ?int $userId): void
    {
        $usage = $response->json('usageMetadata');

        if (! $response->successful() || ! is_array($usage)) {
            return;
        }

        $promptTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $completionTokens = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);
        $totalTokens = (int) ($usage['totalTokenCount'] ?? ($promptTokens + $completionTokens));

        try {
            AiUsageLog::query()->create([
                'user_id' => $userId,
                'feature' => $feature->value,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'cost_usd' => $this->estimateCost($model, $promptTokens, $completionTokens),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to record AI usage.', ['message' => $exception->getMessage()]);
        }
    }

    public function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config("integrations.ai.pricing.{$model}") ?? config('integrations.ai.pricing.default');

        $input = (float) ($pricing['input'] ?? 0);
        $output = (float) ($pricing['output'] ?? 0);

        return round(($promptTokens * $input + $completionTokens * $output) / 1_000_000, 6);
    }
}
