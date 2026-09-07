<?php

return [
    'visual_fallback_enabled' => (bool) env('NEWS_VISUAL_FALLBACK_ENABLED', false),
    'chrome_path' => env('CHROME_PATH'),
    'native_curl_path' => env('NATIVE_CURL_PATH'),
    'ca_bundle_path' => env('NEWS_CA_BUNDLE_PATH'),
    'connect_timeout_seconds' => (int) env('NEWS_CONNECT_TIMEOUT_SECONDS', 60),
    'request_timeout_seconds' => (int) env('NEWS_REQUEST_TIMEOUT_SECONDS', 60),
];
