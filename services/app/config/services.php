<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://ai-service:8000/analyze'),
        'status_url' => env('AI_STATUS_URL', 'http://ai-service:8000/status'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 330),
    ],
];
