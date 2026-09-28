<?php
declare(strict_types=1);

// Copy to config.local.php. Never commit real credentials or the application key.
return [
    'environment' => 'local',
    'base_path' => '', // Empty when public/ is the document root; no trailing slash.
    'timezone' => 'Australia/Sydney',
    'contact_email' => 'hello@clubconnect.example',
    'contact_is_example' => true,
    'demo_mode' => true,
    'session_timeout' => 1800,
    'https_required' => false, // Local loopback HTTP only. Hosted use MUST be true.
    'app_key' => 'REPLACE_WITH_64_HEX_CHARACTERS_FROM_RANDOM_BYTES',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'clubconnect',
        'user' => 'clubconnect_app',
        'password' => 'REPLACE_WITH_YOUR_LOCAL_DATABASE_PASSWORD',
    ],
];
