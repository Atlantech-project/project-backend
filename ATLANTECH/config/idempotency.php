<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile write idempotency (IDEMP-01, CT-01 section 4)
    |--------------------------------------------------------------------------
    |
    | Every write made with a mobile Bearer token carries an `Idempotency-Key`. The first response is kept for `ttl_hours`
    | and replayed when the same key comes back with the same request. The entries live in the cache store named here
    | (null: the application's default store, Redis in production), so there is no table.
    |
    */

    'store' => env('IDEMPOTENCY_CACHE_STORE'),

    'ttl_hours' => 24,

    /*
    | A request holds the lock for its key while it runs: at most `lock_seconds`. A second request with the same key waits up
    | to `lock_wait_seconds` for it, then replays the result, or answers 503 with Retry-After when the first is still running.
    */

    'lock_seconds' => 30,

    'lock_wait_seconds' => 5,

    /*
    | Route names that never use the header (patterns allowed). `POST /sync/events` (CT-07) keeps `client_event_id`:
    | the route that P5 builds must carry this name.
    */

    'exempt_routes' => [
        'sync.events.store',
    ],

    /*
    | Responses that are never stored, besides every 5xx: 401 (not signed in), and the refusals that pass with time, so a retry
    | with the same key runs the write again (request timeout, licence READ_ONLY, app update required, too many attempts).
    */

    'unstored_statuses' => [401, 408, 423, 426, 429],

];
