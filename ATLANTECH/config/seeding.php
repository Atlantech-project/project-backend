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

    'demo_port' => env('SEED_DEMO_PORT', env('APP_ENV') !== 'production'),

    'demo_user_password' => env('DEMO_USER_PASSWORD'),

];
