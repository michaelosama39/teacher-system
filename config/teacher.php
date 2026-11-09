<?php

use App\Enums\BillingMode;

return [

    /*
    |--------------------------------------------------------------------------
    | Teacher Identity
    |--------------------------------------------------------------------------
    |
    | Every deployment of this application belongs to exactly one teacher.
    | All per-teacher differences live here and are driven by the .env file.
    | Never add per-teacher code paths: add a config value or feature flag.
    |
    */

    'name' => env('TEACHER_NAME', 'Teacher'),

    'phone' => env('TEACHER_PHONE'),

    'subject' => env('TEACHER_SUBJECT'),

    'currency' => env('TEACHER_CURRENCY', 'EGP'),

    'billing_mode' => env('TEACHER_BILLING_MODE', BillingMode::Monthly->value),

    'student_code_prefix' => env('STUDENT_CODE_PREFIX', 'ST'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'features' => [
        'self_registration' => (bool) env('FEATURE_SELF_REGISTRATION', false),
        'whatsapp' => (bool) env('FEATURE_WHATSAPP', false),
    ],

];
