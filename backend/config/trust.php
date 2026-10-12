<?php

/*
|--------------------------------------------------------------------------
| Trust Score
|--------------------------------------------------------------------------
|
| The score is rule-based and explainable: every student starts at the
| baseline and each component adds or removes points, never beyond its cap.
| Cap shares follow the proposal weights (repayment 40%, marketplace 20%,
| vouches 15%, account 10%, engagement 15%) on a +/-500 range around the
| baseline. A learned model may later calibrate the point values below,
| which is why they live in config and not in code.
|
*/

return [

    'baseline' => 500,
    'min' => 0,
    'max' => 1000,

    /*
     * Highest matching tier wins. max_loan is in dollars.
     */
    'tiers' => [
        ['name' => 'trusted', 'min_score' => 700, 'max_loan' => 100],
        ['name' => 'basic', 'min_score' => 400, 'max_loan' => 20],
        ['name' => 'restricted', 'min_score' => 0, 'max_loan' => 0],
    ],

    'components' => [

        'repayment' => [
            'cap' => 200,
            'events' => [
                'loan_repaid_on_time' => 40,
                'loan_repaid_late' => 10,
                'loan_overdue' => -60,
                'loan_defaulted' => -150,
            ],
        ],

        'marketplace' => [
            'cap' => 100,
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

        'account' => [
            'cap' => 50,
            'verified_email' => 25,
            'unverified_email' => -25,
            'points_per_30_days' => 5,
            'age_cap' => 25,
        ],

        'engagement' => [
            'cap' => 75,
            'events' => [
                'group_joined' => 5,
                'event_attended' => 10,
            ],
        ],

    ],

];
