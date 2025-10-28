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
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'google' => [
        'project_id'   => env('GOOGLE_PROJECT_ID'),
        'region'       => env('GOOGLE_SPEECH_REGION', 'us-central1'),
        'model'        => env('GOOGLE_SPEECH_MODEL', 'chirp'),

        // Credentials from the Service Account JSON file
        'credentials'  => [
            'type'           => 'service_account',
            'project_id'     => env('GOOGLE_PROJECT_ID'),
            'private_key_id' => env('GOOGLE_PRIVATE_KEY_ID'),
            'private_key'    => env('GOOGLE_PRIVATE_KEY'),
            'client_email'   => env('GOOGLE_CLIENT_EMAIL'),
            'client_id'      => env('GOOGLE_CLIENT_ID'),
            'auth_uri'       => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri'      => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => env('GOOGLE_CLIENT_X509_CERT_URL'),
        ],
    ],

    'azure' => [
        'api_key' => env('AZURE_API_KEY'),
        'region' => env('AZURE_SPEECH_REGION'),
    ],

    'watson' => [
        'api_key' => env('WATSON_API_KEY'),
        'url' => env('WATSON_SPEECH_URL'),
    ],

    'assemblyai' => [
        'api_key' => env('ASSEMBLYAI_API_KEY', ''),
        'region' => env('ASSEMBLYAI_REGION', 'US'), 
        'base_url' => env('ASSEMBLYAI_BASE_URL', 'api.assemblyai.com'),
    ],

    'keygen' => [
        'api_url' => env('KEYGEN_API_URL', 'https://api.keygen.sh'),
        'account_id' => env('KEYGEN_ACCOUNT_ID', 'f2ca6242-a55c-4949-9529-d7d591d3271a'),
    ],

    'ztp' => [
        'polycom' => [
            'api_url' => env('POLYCOM_API_URL', 'https://api.ztp.poly.com/v1'),
        ]
    ],
    'ceretax' => [
        'env' => env('CERETAX_ENV', 'sandbox'),
        'sandbox_url' => env('CERETAX_SANDBOX_URL', "https://calc.cert.ceretax.net/"),
        'prod_url' => env('CERETAX_PRODUCTION_URL', "https://calc.prod.ceretax.net/"),
        'sandbox_api_key' => env('CERETAX_SANDBOX_API_KEY', ""),
        'prod_api_key' => env('CERETAX_PROD_API_KEY', ""),
        'sandbox_client_profile_id' => env('CERETAX_SANDBOX_CLIENT_PROFILE_ID', "default"),
        'prod_client_profile_id' => env('CERETAX_PROD_CLIENT_PROFILE_ID', "default"),
    ],

];
