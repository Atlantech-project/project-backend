<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Errors\ApiProblemException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The version token of a mutable record: its `updated_at` (DATETIME(6)) as an opaque string, e.g.
 * `2026-11-02T10:15:04.123456Z` (FR-GL-05, DEC-57, CT-01 section 5). Resources return it as `version`; an update sends
 * it back; a stale value is refused with 409 VERSION_CONFLICT and `current: { id, version }`.
 *
 * Two checks use the same comparison: the `version.token` middleware (its place in the write chain, P2-D2) and
 * `lockAndAssert` inside the endpoint's transaction, which makes the check race-safe (NFR-CONC-1).
 */
class VersionToken
{
    /** The only shape a client may send back. */
    public const string PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/';

    /**
     * Validation rules for the `version` member of an update request (422 with `validation.*` keys when it is missing or malformed).
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'regex:'.self::PATTERN];
    }

    public static function format(CarbonInterface $at): string
    {
        return $at->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    public static function of(Model $model): string
    {
        $updatedAt = $model->getAttribute($model->getUpdatedAtColumn());

        if (! $updatedAt instanceof CarbonInterface) {
            throw new LogicException($model::class.' has no '.$model->getUpdatedAtColumn().' to use as a version token.');
        }

        return self::format($updatedAt);
    }

    public static function matches(Model $model, string $sent): bool
    {
        return hash_equals(self::of($model), $sent);
    }

    /**
     * @throws ApiProblemException 409 VERSION_CONFLICT carrying the stored `id` and `version`.
     */
    public static function assertCurrent(Model $model, string $sent): void
    {
        if (! self::matches($model, $sent)) {
            throw ApiProblemException::versionConflict(['id' => $model->getKey(), 'version' => self::of($model)]);
        }
    }

    /**
     * Read the row under `SELECT … FOR UPDATE` and compare, inside the caller's transaction. Returns the locked row,
     * which the endpoint then changes and saves before it commits.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     *
     * @throws LogicException Outside a transaction (a lock held for one statement protects nothing).
     * @throws ApiProblemException 409 VERSION_CONFLICT.
     */
    public static function lockAndAssert(Model $model, string $sent): Model
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('VersionToken::lockAndAssert must run inside a transaction.');
        }

        $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

        self::assertCurrent($locked, $sent);

        return $locked;
    }
}
