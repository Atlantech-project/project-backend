<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Requests\AuditLogIndexRequest;
use App\Modules\Shared\Http\Resources\AuditLogCollection;
use App\Modules\Shared\Http\Validation\ZonedDateTime;
use App\Modules\Shared\Models\AuditLog;

/**
 * Audit log browsing for the Port Admin (P2-F8, FR-MD-16); the route carries `ability:audit.read`. Master-data history is this list filtered by entity.
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/v1/audit-logs: a page, newest first; 401, 403, 422.
     */
    public function index(AuditLogIndexRequest $request): AuditLogCollection
    {
        $filters = $request->validated();

        $query = AuditLog::query()
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->select('audit_logs.*', 'users.name as actor_name', 'users.username as actor_username')
            ->when(isset($filters['entity_type']), fn ($query) => $query->where('audit_logs.entity_type', $filters['entity_type']))
            ->when(isset($filters['entity_id']), fn ($query) => $query->where('audit_logs.entity_id', $filters['entity_id']))
            ->when(isset($filters['actor_id']), fn ($query) => $query->where('audit_logs.actor_id', $filters['actor_id']))
            ->when(isset($filters['action']), fn ($query) => $query->where('audit_logs.action', $filters['action']))
            ->when(isset($filters['from']), fn ($query) => $query->where('audit_logs.occurred_at', '>=', ZonedDateTime::toUtc($filters['from'])))
            ->when(isset($filters['to']), fn ($query) => $query->where('audit_logs.occurred_at', '<=', ZonedDateTime::toUtc($filters['to'])))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($actor) => $actor->where('users.username', 'like', $like)->orWhere('users.name', 'like', $like));
            })
            ->orderByDesc('audit_logs.occurred_at')
            ->orderByDesc('audit_logs.id');

        return new AuditLogCollection($request->page()->paginate($query));
    }
}
