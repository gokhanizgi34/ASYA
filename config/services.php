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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'image_size' => env('OPENAI_IMAGE_SIZE', '1536x1024'),
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
    ],
    'gemini' => [
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
    ],
    'external_trends' => [
        'google_geo' => env('GOOGLE_TRENDS_GEO', 'TR'),
        'google_rss_url' => env('GOOGLE_TRENDS_RSS_URL', 'https://trends.google.com/trending/rss?geo=TR'),
        'x_endpoint' => env('X_TRENDS_ENDPOINT', 'https://api.x.com/2/trends/by/woeid'),
        'x_rss_url' => env('X_TRENDS_RSS_URL', 'https://www.twitter-trending.com/rss/feed?c=turkey&gmt_z=Europe/Istanbul&l=tr'),
        'x_web_url' => env('X_TRENDS_WEB_URL', 'https://trends24.in/turkey/'),
        'x_woeid' => (int) env('X_TRENDS_WOEID', 23424969),
        'x_max_trends' => (int) env('X_TRENDS_MAX', 10),
        'max_items_per_run' => (int) env('EXTERNAL_TRENDS_MAX_ITEMS', 20),
        'min_traffic' => (int) env('EXTERNAL_TRENDS_MIN_TRAFFIC', 5000),
    ],
];
