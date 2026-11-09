<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['blocker_id', 'blocked_id'];

    /**
     * True when either student has blocked the other.
     */
    public static function betweenEither(User $a, User $b): bool
    {
        return self::query()
            ->where(fn ($q) => $q->where('blocker_id', $a->id)->where('blocked_id', $b->id))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b->id)->where('blocked_id', $a->id))
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
