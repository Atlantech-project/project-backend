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
    'device_revoked' => 'This device has been revoked by your port administrator. Contact your port administrator to use it again.',
    'app_update_required' => 'This version of the app is no longer supported. Update the app to continue.',
    'license_read_only' => 'The port license is read-only: you can view data, but changes are blocked. Contact your port administrator.',
    'plan_limit_exceeded' => 'This would exceed a limit of the port plan. Contact your port administrator.',
    'too_many_attempts' => 'Too many attempts. Wait a moment and try again.',
    'idempotency_key_reused' => 'This request key was already used for a different request. Send the request again with a new key.',
    'service_unavailable' => 'The service is temporarily unavailable. Try again in a few minutes.',
    'server_error' => 'Something went wrong on our side. Try again; if the problem continues, contact support.',
    'bad_request' => 'The request could not be processed. Check what you sent and try again.',
    'conflict' => 'This change conflicts with the current state of the record.',
    'auth' => [
        'invalid_credentials' => 'The username or password is incorrect.',
    ],
    'users' => [
        'cannot_deactivate_self' => 'You cannot deactivate your own account.',
        'last_port_admin' => 'At least one active Port Admin must remain.',
    ],
    'tug_masters' => [
        'tug_taken' => 'This tug already has a tug master. Unassign that tug master first.',
    ],
    'port_shifts' => [
        'overlap' => 'This shift overlaps another active shift.',
        'in_use' => 'Schedules from today on use this shift, so its times cannot change and it cannot be deactivated.',
    ],
    'pilot_rank_limits' => [
        'order' => 'A more senior rank must be allowed at least the vessel length allowed to a more junior rank.',
    ],
    'schedules' => [
        'past_date' => 'A schedule date cannot be before today in port time.',
        'person_unavailable' => 'This person is not active in this role.',
        'shift_inactive' => 'This shift is not active.',
        'duplicate' => 'This person is already on this shift on this date.',
    ],
    'terminals' => [
        'terminal_inactive' => 'This terminal is inactive. Its data can be viewed but not changed.',
    ],
    'equipment' => [
        'units_exceed_quantity' => 'Units in maintenance and blocked units together cannot exceed the quantity.',
        'not_container_terminal' => 'Equipment can only be managed on a container terminal.',
    ],
    'yard' => [
        'not_container_terminal' => 'A container yard can only be managed on a container terminal.',
        'zone_has_blocks' => 'This zone still has blocks. Delete its blocks first.',
        'run_in_progress' => 'A yard recommendation is running for this terminal. Try again when it has finished.',
        'slots_in_use' => 'Some slots that would be removed hold a container, are used by a recommendation or are blocked.',
        'slot_occupied' => 'A slot that holds a container cannot be blocked.',
        'stack_not_found' => 'This block has no slots in that bay and row.',
        'layout_too_large' => 'A block cannot have more than 5000 slots.',
    ],
    'berths' => [
        'terminal_in_use' => 'This terminal cannot be removed from the berth because safety constraints or recommendations use the pair.',
        'terminal_inactive' => 'A berth cannot be linked to an inactive terminal.',
        'datum_mismatch' => 'The berth depth datum must match the depth datum of the navigation channel.',
        'loa_exceeds_length' => 'The maximum vessel length cannot be longer than the berth.',
    ],
];
