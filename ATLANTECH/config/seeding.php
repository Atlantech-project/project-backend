<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo port (P1-B6)
    |--------------------------------------------------------------------------
    |
    | Whether `db:seed` loads the Damietta-scale demo port and its demo users. It is on outside production and
    | off in production unless SEED_DEMO_PORT=true. The demo users share the DEMO_USER_PASSWORD password; when
    | that is empty their password hash is random and nobody can sign in.
    |
    */

    'demo_port' => env('SEED_DEMO_PORT', env('APP_ENV', 'production') !== 'production'),

    'demo_user_password' => env('DEMO_USER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | First Port Admin (P2-E1)
    |--------------------------------------------------------------------------
    |
    | The first Port Admin of a real port, created by FirstPortAdminSeeder only when all three values are set and no
    | active Port Admin exists. Never put these values in a tracked file; do not use the demo username `port.gm`.
    |
    */

    'first_port_admin' => [
        'username' => env('FIRST_PORT_ADMIN_USERNAME'),
        'name' => env('FIRST_PORT_ADMIN_NAME'),
        'password' => env('FIRST_PORT_ADMIN_PASSWORD'),
    ],

];
