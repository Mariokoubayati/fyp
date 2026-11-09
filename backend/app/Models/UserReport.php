<?php

namespace App\Models;

use App\Enums\ReportReason;
use Database\Factories\UserReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model
{
    /** @use HasFactory<UserReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['reporter_id', 'reported_id', 'conversation_id', 'reason', 'details'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
