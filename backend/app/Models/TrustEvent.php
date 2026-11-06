<?php

namespace App\Models;

use App\Enums\TrustEventType;
use Database\Factories\TrustEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'counterparty_id', 'type', 'subject_type', 'subject_id', 'value', 'discount_reason', 'occurred_at'])]
class TrustEvent extends Model
{
    /** @use HasFactory<TrustEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The other student involved (lender, buyer, rater or voucher).
     *
     * @return BelongsTo<User, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TrustEventType::class,
            'counterparty_id' => 'integer',
            'value' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
