<?php

return [
    'facebook' => [
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'enabled' => env('SOCIAL_FACEBOOK_ENABLED', false),
    ],

    'instagram' => [
        'account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'enabled' => env('SOCIAL_INSTAGRAM_ENABLED', false),
    ],

    'blog_url' => env('SOCIAL_BLOG_URL', 'https://vidaeneljardin.com'),
];
