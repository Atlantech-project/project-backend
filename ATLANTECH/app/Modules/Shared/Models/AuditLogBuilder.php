<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Query builder of {@see AuditLog}: the audit log is append-only (NFR-SEC-6), so every statement that changes or removes rows is refused.
 *
 * @extends Builder<AuditLog>
 */
class AuditLogBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): never
    {
        $this->refuse();
    }

    public function delete(): never
    {
        $this->refuse();
    }

    public function forceDelete(): never
    {
        $this->refuse();
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        $this->refuse();
    }

    private function refuse(): never
    {
        throw new LogicException('audit_logs is append-only (NFR-SEC-6): rows are never updated or deleted.');
    }
}
