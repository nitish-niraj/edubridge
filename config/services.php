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

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT'),
    ],

    'phonepe' => [
        'client_id'      => env('PHONEPE_CLIENT_ID'),
        'client_version' => (int) env('PHONEPE_CLIENT_VERSION', 1),
        'client_secret'  => env('PHONEPE_CLIENT_SECRET'),
        'env'            => env('PHONEPE_ENV', 'PRODUCTION'),
    ],

    'razorpay' => [
        'key'            => env('RAZORPAY_KEY'),
        'secret'         => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', env('RAZORPAY_SECRET')),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'api_key'     => env('TWILIO_API_KEY'),
        'api_secret'  => env('TWILIO_API_SECRET'),
        'auth_token'  => env('TWILIO_AUTH_TOKEN'),
        'sid'         => env('TWILIO_ACCOUNT_SID'),
        'token'       => env('TWILIO_AUTH_TOKEN'),
        'sms_from'    => env('TWILIO_SMS_FROM'),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'browser_dsn' => env('VITE_SENTRY_DSN'),
        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
        'browser_traces_sample_rate' => (float) env('VITE_SENTRY_TRACES_SAMPLE_RATE', 0.1),
    ],

    'ga' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
    ],

    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
    ],

    'contact' => [
        'to' => env('CONTACT_FORM_TO_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'smtp_api_url' => env('BREVO_SMTP_API_URL', 'https://api.brevo.com/v3/smtp/email'),
    ],

];
