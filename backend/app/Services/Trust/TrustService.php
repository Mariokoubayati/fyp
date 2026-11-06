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
        private readonly TrustHistoryBuilder $history,
        private readonly TrustModelClient $model,
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
     * The score and the tier a decision should use right now: the rules, then the AI model's risk check.
     */
    public function current(User $user): TrustScore
    {
        $signals = $this->collusion->signalsFor($user, now());

        return $this->withModelRisk($user, $this->rulesOnly($user, $signals), $signals);
    }

    /**
     * The explainable rule-based score alone (what is stored and logged).
     */
    public function rulesOnly(User $user, ?CollusionSignals $signals = null): TrustScore
    {
        return $this->calculator->calculate(
            $user,
            $user->trustEvents()->get(),
            now(),
            $signals ?? $this->collusion->signalsFor($user, now()),
        );
    }

    /**
     * Ask the AI model how risky each loan size the rules allow would be, and lower the tier
     * to the largest size it considers safe. The model can only lower a tier, never raise it,
     * and if it is unavailable the rules decide alone.
     */
    private function withModelRisk(User $user, TrustScore $rules, CollusionSignals $signals): TrustScore
    {
        if ($rules->maxLoan === 0 || ! $this->model->enabled()) {
            return $rules;
        }

        $tiers = collect(config('trust.tiers'))
            ->filter(fn (array $tier): bool => $tier['max_loan'] > 0 && $tier['max_loan'] <= $rules->maxLoan)
            ->sortBy('max_loan')
            ->values();

        $result = $this->model->assess(
            $this->history->build($user, now(), $signals),
            $tiers->pluck('max_loan')->all(),
        );

        if ($result === null) {
            return $rules;
        }

        $allowed = collect($result['assessments'])->keyBy(fn (array $a): int => (int) $a['amount']);
        $model = ['version' => $result['version'], 'p_default' => (float) ($result['assessments'][0]['p_default'] ?? 0)];

        // The largest tier whose loan size, and every smaller one, the model allows.
        $safe = null;
        foreach ($tiers as $tier) {
            if (! ($allowed[$tier['max_loan']]['allowed'] ?? false)) {
                break;
            }
            $safe = $tier;
        }

        $safe ??= $this->calculator->tierFor(0);

        if ($safe['max_loan'] === $rules->maxLoan) {
            return new TrustScore($rules->score, $rules->tier, $rules->maxLoan, $rules->breakdown, $rules->flags, $model);
        }

        return new TrustScore(
            $rules->score,
            $safe['name'],
            $safe['max_loan'],
            $rules->breakdown,
            [...$rules->flags, TrustScoreCalculator::FLAG_MODEL_RISK],
            $model,
        );
    }

    /**
     * Recompute the score, store it on the student and log it when it changed.
     */
    public function refresh(User $user, ?string $trigger = null): TrustScore
    {
        $result = $this->rulesOnly($user);
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
