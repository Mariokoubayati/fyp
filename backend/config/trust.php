<?php

/*
|--------------------------------------------------------------------------
| Trust Score
|--------------------------------------------------------------------------
|
| The score is rule-based and explainable: every student starts at the
| baseline and each component adds or removes points, never beyond its cap.
|
| Components follow the three signals named in the proposal (transaction
| history, friend verifications, repayment). Cap shares on the +/-500 range
| around the baseline: repayment 60% (300), marketplace 25% (125),
| vouches 15% (75). Engagement and account age were removed for fairness:
| they reward free time and seniority rather than reliability. Account age
| survives only as a short waiting period before the top tier.
|
| A learned model may later calibrate the point values below, which is why
| they live in config and not in code.
|
*/

return [

    'baseline' => 500,
    'min' => 0,
    'max' => 1000,

    /*
     * Highest matching tier wins. max_loan is in dollars. A new verified
     * student sits at the baseline (500), which is the basic tier.
     */
    'tiers' => [
        ['name' => 'trusted', 'min_score' => 700, 'max_loan' => 100],
        ['name' => 'basic', 'min_score' => 400, 'max_loan' => 20],
        ['name' => 'restricted', 'min_score' => 0, 'max_loan' => 0],
    ],

    'components' => [

        'repayment' => [
            'cap' => 300,
            'events' => [
                'loan_repaid_on_time' => 40,
                'loan_repaid_late' => 10,
                'loan_overdue' => -60,
                'loan_defaulted' => -150,
            ],
        ],

        'marketplace' => [
            'cap' => 125,
            'events' => [
                'sale_completed' => 20,
                'purchase_completed' => 10,
            ],
            // A rating event carries 1-5 stars: (stars - neutral) * points_per_star.
            'rating' => ['neutral_stars' => 3, 'points_per_star' => 10],
        ],

        'vouches' => [
            'cap' => 75,
            'events' => [
                'vouch_received' => 25,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-collusion
    |--------------------------------------------------------------------------
    |
    | No money moves through the app, so two friends could fake loans, sales
    | and vouches to push each other up. These rules limit what a single
    | partner, or a small closed circle, can do for a score. Penalties are
    | never limited, only rewards.
    |
    */

    'anti_collusion' => [

        // Rewards from the same counterparty, per component, inside a rolling window.
        'max_rewards_per_pair' => 2,
        'pair_window_days' => 90,

        // A loan repaid this fast after the cash changed hands earns nothing.
        'min_loan_hours' => 48,

        // Repayment rewards scale with the number of different lenders: full weight from this many.
        'full_weight_lenders' => 3,

        // Vouches given back to someone who vouched for you count this much.
        'mutual_vouch_weight' => 0.5,

        // Vouches only count from verified students at or above this score.
        'min_voucher_score' => 400,

        // Loans in this window are checked for circular lending (A->B->A, A->B->C->A).
        'circular_window_days' => 90,

        // Accounts younger than this cannot reach the top tier.
        'min_account_days_for_top_tier' => 14,
    ],

];
