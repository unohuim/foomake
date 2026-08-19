<?php

return [
    'google' => [
        'account_chooser_url' => env('GOOGLE_ACCOUNT_CHOOSER_URL', 'https://accounts.google.com/AccountChooser'),
        'oauth_authorize_url' => env('GOOGLE_OAUTH_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'oauth_token_url' => env('GOOGLE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'search_console_base_url' => env('GOOGLE_SEARCH_CONSOLE_BASE_URL', 'https://www.googleapis.com/webmasters/v3'),
    ],
];
