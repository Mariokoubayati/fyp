<?php

namespace App\Services\Trust;

use App\Enums\TrustEventType;
use App\Models\TrustEvent;
use App\Models\TrustScoreLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single entry point other features use to feed the trust score.
 */
class TrustService
{
    public function __construct(private readonly TrustScoreCalculator $calculator) {}

    /**
     * Record that something trust-relevant happened, then refresh the stored score.
     *
     * Recording is idempotent per (student, event type, subject), so retries and
     * repeated joins/leaves cannot inflate the score.
     */
    public function record(User $user, TrustEventType $type, Model $subject, ?int $value = null): void
    {
        TrustEvent::firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ],
            ['value' => $value, 'occurred_at' => now()],
        );

        $this->refresh($user, $type->value);
    }

    /**
     * Withdraw an earlier event, e.g. when a vouch is taken back.
     */
    public function forget(User $user, TrustEventType $type, Model $subject): void
    {
        TrustEvent::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->delete();

        $this->refresh($user, 'forgot_'.$type->value);
    }

    /**
     * Compute the current score without storing anything.
     */
    public function current(User $user): TrustScore
    {
        return $this->calculator->calculate($user, $user->trustEvents()->get(), now());
    }

    /**
     * Recompute the score, store it on the student and log it when it changed.
     */
    public function refresh(User $user, ?string $trigger = null): TrustScore
    {
        $result = $this->current($user);
        $lastLogged = $user->trustScoreLogs()->latest('id')->value('score');

        if ($lastLogged === null || $lastLogged !== $result->score) {
            TrustScoreLog::create([
                'user_id' => $user->id,
                'score' => $result->score,
                'tier' => $result->tier,
                'breakdown' => $result->breakdown,
                'trigger' => $trigger,
            ]);
        }

        if ($user->trust_score !== $result->score) {
            $user->forceFill(['trust_score' => $result->score])->saveQuietly();
        }

        return $result;
    }
}
