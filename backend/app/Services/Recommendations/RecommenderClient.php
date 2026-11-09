<?php

namespace App\Services\Recommendations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the recommender endpoints of the ML service. Every failure returns null,
 * so callers fall back to the SQL ordering.
 */
class RecommenderClient
{
    private const DOWN_KEY = 'recommender:down';

    public function enabled(): bool
    {
        return (bool) config('services.recommender.enabled');
    }

    /**
     * Candidate ids in ranked order, or null when the service is off or unreachable.
     *
     * @param  'groups'|'listings'  $kind
     * @param  array<string, mixed>  $user
     * @param  list<array<string, mixed>>  $candidates
     * @return list<int>|null
     */
    public function rank(string $kind, array $user, array $candidates, int $now): ?array
    {
        if (! $this->enabled() || Cache::has(self::DOWN_KEY)) {
            return null;
        }

        if ($candidates === []) {
            return [];
        }

        $payload = ['now' => $now, 'user' => $user, 'candidates' => $candidates];
        $key = "recommender:{$kind}:".sha1(json_encode($payload));

        return Cache::remember($key, now()->addMinutes(10), function () use ($kind, $payload): ?array {
            try {
                $response = Http::baseUrl(config('services.recommender.url'))
                    ->timeout(config('services.recommender.timeout'))
                    ->when(config('services.recommender.token'), fn ($request, $token) => $request->withToken($token))
                    ->acceptJson()
                    ->post("/recommend/{$kind}", $payload);
            } catch (ConnectionException $exception) {
                return $this->markDown($exception->getMessage());
            }

            if (! $response->successful()) {
                return $this->markDown("HTTP {$response->status()}");
            }

            return collect($response->json('ranked', []))->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        });
    }

    /**
     * Stop calling a broken service for a minute, so one outage does not slow every request.
     */
    private function markDown(string $reason): null
    {
        Log::warning('Recommender unavailable, using SQL ordering.', ['reason' => $reason]);
        Cache::put(self::DOWN_KEY, true, now()->addMinute());

        return null;
    }
}
