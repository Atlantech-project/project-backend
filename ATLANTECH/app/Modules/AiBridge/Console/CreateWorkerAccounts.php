<?php

namespace App\Modules\AiBridge\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates the six AI worker MySQL accounts (DEC-08): applies the roles in
 * database/grants/workers.sql, then creates or updates each account with its
 * password from the environment and gives it its role. Safe to run again.
 */
class CreateWorkerAccounts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'atlantech:worker-accounts {--host=% : Host the worker accounts connect from}';

    /**
     * @var string
     */
    protected $description = 'Create the AI worker MySQL accounts and give each its DEC-08 role';

    /**
     * Account name => role name, in the order of the DEC-08 matrix.
     *
     * @var array<string, string>
     */
    public const ACCOUNTS = [
        'w_part1' => 'r_part1',
        'w_part2' => 'r_part2',
        'w_yard' => 'r_yard',
        'w_edi' => 'r_edi',
        'w_validate' => 'r_validate',
        'w_part3' => 'r_part3',
    ];

    public function handle(): int
    {
        $passwords = [];
        foreach (array_keys(self::ACCOUNTS) as $account) {
            $passwords[$account] = (string) config("services.ai_workers.db_passwords.{$account}", '');
        }

        $missing = array_keys(array_filter($passwords, fn (string $password): bool => $password === ''));
        if ($missing !== []) {
            $this->error('Missing passwords: '.implode(', ', array_map(self::passwordVariable(...), $missing)));

            return self::FAILURE;
        }

        DB::unprepared(file_get_contents(database_path('grants/workers.sql')));

        $pdo = DB::getPdo();
        $host = $pdo->quote((string) $this->option('host'));

        foreach (self::ACCOUNTS as $account => $role) {
            $user = $pdo->quote($account).'@'.$host;
            $password = $pdo->quote($passwords[$account]);

            DB::statement("CREATE USER IF NOT EXISTS {$user} IDENTIFIED BY {$password}");
            DB::statement("ALTER USER {$user} IDENTIFIED BY {$password}");
            DB::statement("GRANT {$pdo->quote($role)} TO {$user}");
            DB::statement("SET DEFAULT ROLE {$pdo->quote($role)} TO {$user}");

            $this->line("{$account} → {$role}");
        }

        $this->info('Worker accounts are ready.');

        return self::SUCCESS;
    }

    /**
     * The environment variable that holds an account's password, e.g. WORKER_PART1_PASSWORD.
     */
    public static function passwordVariable(string $account): string
    {
        return 'WORKER_'.strtoupper(substr($account, 2)).'_PASSWORD';
    }
}
