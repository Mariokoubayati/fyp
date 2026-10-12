<?php

/*
|--------------------------------------------------------------------------
| Peer-to-Peer Lending
|--------------------------------------------------------------------------
|
| UniCircle only keeps a ledger: no money moves through the app. Both
| students confirm when cash changes hands and when it is paid back.
|
*/

return [

    'min_amount' => 5,
    'max_amount' => 100,

    /*
     * Longest repayment period a borrower can ask for, in days.
     */
    'max_days' => 60,

    /*
     * Loans a student may have open as borrower at once (requested, accepted, active).
     */
    'max_open_loans' => 2,

    /*
     * Days after the due date before an overdue loan counts as defaulted.
     */
    'default_after_days' => 14,

];
