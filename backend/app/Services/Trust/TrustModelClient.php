<?php

namespace App\Services\Trust;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the AI trust service. Every failure returns null so callers fall back to the rules.
 */
class TrustModelClient
{
    private const DOWN_KEY = 'trust-model:down';

    public function enabled(): bool
    {
        return (bool) config('services.trust_model.enabled');
    }

    /**
     * Default risk for each amount, or null when the service is off or unreachable.
     *
     * @param  array<string, mixed>  $history
     * @param  list<int>  $amounts
     * @return array{version: string, threshold: float, assessments: array<int, array{amount: float, p_default: float, allowed: bool}>}|null
     */
    public function assess(array $history, array $amounts): ?array
    {
        if (! $this->enabled() || Cache::has(self::DOWN_KEY)) {
            return null;
        }

        $key = 'trust-model:'.sha1(json_encode([$history, $amounts]));

        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        try {
            $response = Http::baseUrl(config('services.trust_model.url'))
                ->timeout(config('services.trust_model.timeout'))
                ->when(config('services.trust_model.token'), fn ($request, $token) => $request->withToken($token))
                ->acceptJson()
                ->post('/trust/assess', ['history' => $history, 'amounts' => $amounts]);
        } catch (ConnectionException $exception) {
            return $this->markDown($exception->getMessage());
        }

        if (! $response->successful()) {
            return $this->markDown("HTTP {$response->status()}");
        }

        $result = [
            'version' => (string) $response->json('model_version'),
            'threshold' => (float) $response->json('risk_threshold'),
            'assessments' => $response->json('assessments', []),
        ];

        Cache::put($key, $result, now()->addMinutes(10));

        return $result;
    }

    /**
     * Stop calling a broken service for a minute, so one outage does not slow every request.
     */
    private function markDown(string $reason): null
    {
        Log::warning('Trust model unavailable, using rules only.', ['reason' => $reason]);
        Cache::put(self::DOWN_KEY, true, now()->addMinute());

        return null;
    }
}
