<?php

/*
|--------------------------------------------------------------------------
| Error messages (English)
|--------------------------------------------------------------------------
|
| One message per stable error code (App\Modules\Shared\Errors\ErrorCode, CT-01
| contracts/error-codes.md), keyed by the code in lower snake case. The text is
| the RFC 9457 "detail" when the thrower gives none. Feature-specific keys go
| in an area group: errors.<area>.<name>, e.g. 'berth' => ['loa_exceeds_length' => ...].
| Keys never change after CT-01 is approved; lang/ar follows with FR-GL-09.
|
*/

return [
    'validation_failed' => 'Some of the data you sent is not valid. Check the listed fields and try again.',
    'version_conflict' => 'This record was changed by another user. Reload it and apply your changes again.',
    'unauthenticated' => 'You are not signed in, or your session has expired. Sign in and try again.',
    'session_expired' => 'Your session security token has expired. Reload the page and try again.',
    'forbidden' => 'You do not have permission to do this.',
    'not_found' => 'The requested item was not found. It may have been removed.',
    'method_not_allowed' => 'This action is not supported at this address.',
    'account_disabled' => 'Your account has been disabled. Contact your port administrator.',
    'password_change_required' => 'You must change your password before you continue.',
    'app_update_required' => 'This version of the app is no longer supported. Update the app to continue.',
    'license_read_only' => 'The port license is read-only: you can view data, but changes are blocked. Contact your port administrator.',
    'plan_limit_exceeded' => 'This would exceed a limit of the port plan. Contact your port administrator.',
    'too_many_attempts' => 'Too many attempts. Wait a moment and try again.',
    'idempotency_key_reused' => 'This request key was already used for a different request. Send the request again with a new key.',
    'service_unavailable' => 'The service is temporarily unavailable. Try again in a few minutes.',
    'server_error' => 'Something went wrong on our side. Try again; if the problem continues, contact support.',
    'bad_request' => 'The request could not be processed. Check what you sent and try again.',
];
