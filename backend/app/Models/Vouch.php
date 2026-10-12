<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vouch extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voucher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function vouchee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vouchee_id');
    }
}
