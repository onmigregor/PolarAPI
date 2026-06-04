<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Database Naming Pattern
    |--------------------------------------------------------------------------
    |
    | Define the prefix and suffix used for dynamic tenant database names.
    | Example: prefix 'www_' + tenant 'v1234a' + suffix 'p' = 'www_v1234ap'
    |
    */
    'prefix' => env('TENANT_DB_PREFIX', 'www_'),
    'suffix' => env('TENANT_DB_SUFFIX', 'p'),
];
