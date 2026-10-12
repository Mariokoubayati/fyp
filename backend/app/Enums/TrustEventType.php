<?php

namespace App\Enums;

enum TrustEventType: string
{
    case LoanRepaidOnTime = 'loan_repaid_on_time';
    case LoanRepaidLate = 'loan_repaid_late';
    case LoanOverdue = 'loan_overdue';
    case LoanDefaulted = 'loan_defaulted';
    case SaleCompleted = 'sale_completed';
    case PurchaseCompleted = 'purchase_completed';
    case RatingReceived = 'rating_received';
    case VouchReceived = 'vouch_received';
    case GroupJoined = 'group_joined';
    case EventAttended = 'event_attended';
}
