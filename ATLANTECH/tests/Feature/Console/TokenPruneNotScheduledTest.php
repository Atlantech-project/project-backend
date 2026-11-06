<?php

use Illuminate\Console\Scheduling\Schedule;

/*
 * Mobile tokens are never deleted (FR-GL-03, P1-C3). `PersonalAccessToken::delete()` is refused on the model only; Sanctum's
 * `sanctum:prune-expired` deletes rows with a query and would erase the history of expired tokens. Do not schedule it.
 */

it('does not schedule sanctum:prune-expired', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command);

    expect($commands->filter(fn (string $command) => str_contains($command, 'sanctum:prune-expired')))->toBeEmpty();
});

it('does not name sanctum:prune-expired in routes/console.php', function () {
    expect(file_get_contents(base_path('routes/console.php')))->not->toContain('sanctum:prune-expired');
});
