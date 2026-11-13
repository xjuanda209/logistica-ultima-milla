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

    /*
    | Asistente de IA (módulo Asistente). El proveedor se cambia en el .env, sin tocar código:
    | IA_PROVEEDOR=gemini|groq, IA_MODELO (vacío = el modelo por defecto del proveedor) e IA_API_KEY.
    */
    'ia' => [
        'proveedor' => env('IA_PROVEEDOR', 'gemini'),
        'modelo' => env('IA_MODELO'),
        'clave' => env('IA_API_KEY'),
        // Segundos que se espera la respuesta antes de decir "El asistente no está disponible".
        'tiempo_limite' => 30,
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
