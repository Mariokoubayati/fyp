<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amount', 'purpose', 'due_date'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lender_id');
    }

    /**
     * The side of the loan the student is on, if any.
     */
    public function roleOf(User $user): ?string
    {
        return match ($user->getKey()) {
            $this->borrower_id => 'borrower',
            $this->lender_id => 'lender',
            default => null,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => LoanStatus::class,
            'lender_handover_at' => 'datetime',
            'borrower_handover_at' => 'datetime',
            'lender_repayment_at' => 'datetime',
            'borrower_repayment_at' => 'datetime',
            'activated_at' => 'datetime',
            'repaid_at' => 'datetime',
        ];
    }
}
