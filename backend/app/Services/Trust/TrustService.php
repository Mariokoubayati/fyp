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
    public function __construct(
        private readonly TrustScoreCalculator $calculator,
        private readonly CollusionDetector $collusion,
    ) {}

    /**
     * Record that something trust-relevant happened, then refresh the stored score.
     *
     * Recording is idempotent per (student, event type, subject), so retries and
     * repeated joins/leaves cannot inflate the score. The counterparty is the other
     * student involved; a discount reason keeps the event on record without counting it.
     */
    public function record(
        User $user,
        TrustEventType $type,
        Model $subject,
        ?int $value = null,
        ?User $counterparty = null,
        ?string $discountReason = null,
    ): void {
        TrustEvent::firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ],
            [
                'counterparty_id' => $counterparty?->id,
                'value' => $value,
                'discount_reason' => $discountReason,
                'occurred_at' => now(),
            ],
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
        return $this->calculator->calculate(
            $user,
            $user->trustEvents()->get(),
            now(),
            $this->collusion->signalsFor($user, now()),
        );
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
                'breakdown' => [...$result->breakdown, 'flags' => $result->flags],
                'trigger' => $trigger,
            ]);
        }

        if ($user->trust_score !== $result->score) {
            $user->forceFill(['trust_score' => $result->score])->saveQuietly();
        }

        return $result;
    }
}
