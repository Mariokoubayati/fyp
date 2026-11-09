<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Notifications\Notification;

class LoanAcceptedNotification extends Notification
{
    public function __construct(public readonly Loan $loan) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'loan_accepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_id' => $this->loan->id,
            'amount' => (float) $this->loan->amount,
            'due_date' => $this->loan->due_date?->toDateString(),
            'other' => $this->loan->lender ? ['id' => $this->loan->lender->id, 'name' => $this->loan->lender->name] : null,
        ];
    }
}
