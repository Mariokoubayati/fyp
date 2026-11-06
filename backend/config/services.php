<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * The AI trust model (Python/Flask service in /ml). When enabled, it can
     * lower a student's tier if it predicts a high default risk; it never raises it.
     * If the service is down the rule-based score is used on its own.
     */
    'trust_model' => [
        'enabled' => (bool) env('TRUST_MODEL_ENABLED', false),
        'url' => env('TRUST_MODEL_URL', 'http://127.0.0.1:5001'),
        'token' => env('TRUST_MODEL_TOKEN'),
        'timeout' => (float) env('TRUST_MODEL_TIMEOUT', 2),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
