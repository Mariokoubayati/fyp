<?php

return [

    /*
    |--------------------------------------------------------------------------
    | University Email Domains
    |--------------------------------------------------------------------------
    |
    | Only addresses on these domains may register. UniCircle is for verified
    | students, so the domain check doubles as the first trust signal.
    | Provide a comma-separated list in UNICIRCLE_EMAIL_DOMAINS to override.
    |
    */

    'email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('UNICIRCLE_EMAIL_DOMAINS', 'ua.edu.lb')),
    ))),

];
