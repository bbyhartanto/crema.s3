<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Shared Crema inter-service contract. WEBHOOK_SECRET secures BOTH
    // directions of the internal webhooks: the inbound catalog:sync-s3 webhook
    // (verified by App\Http\Middleware\VerifyInternalWebhookSignature) and the
    // outbound customer projection push signed in App\Jobs\PushCustomerProjectionToS1.
    // Must match S1's WEBHOOK_SECRET.
    'crema' => [
        'webhook_secret' => env('WEBHOOK_SECRET'),
    ],

    // S3 → S1 customer projection webhook target. On the same VPS in production,
    // set S1_INTERNAL_RESOLVE so the request resolves to loopback per-call
    // (CURLOPT_RESOLVE) without editing /etc/hosts, bypassing Cloudflare while
    // TLS still validates via SNI.
    's1' => [
        'internal_url' => env('S1_INTERNAL_BASE_URL', 'https://roaster.crema.supply'),
        'internal_resolve' => env('S1_INTERNAL_RESOLVE'),
    ],

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

];
