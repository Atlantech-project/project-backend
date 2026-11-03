<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\VersionToken;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "record version" (FR-GL-01, alias `version.token:<routeParam>`, opt-in per route): after the licence
 * step and route binding, before the business rules inside the endpoint. The JSON body must carry the `version` the
 * client last read; a stale value answers 409 VERSION_CONFLICT with `current: { id, version }` (CT-01 section 5).
 * The endpoint repeats the comparison on the locked row with `VersionToken::lockAndAssert` (P2-D4).
 *
 * Every refusal is audited as REJECTED_ATTEMPT on the record (FR-GL-02): the early 409 and the 422 here, and the 409
 * from `lockAndAssert`, read from the response on its way out. This middleware sits outside the endpoint's transaction,
 * so by then the refused write has rolled back and the audit row, written in its own transaction, is kept.
 *
 * Reads (GET, HEAD, OPTIONS) pass. The position in the order is fixed by the priority list in `bootstrap/app.php`.
 */
class EnsureRecordVersion
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $parameter  Name of the route parameter bound to the record, e.g. `version.token:user`.
     *
     * @throws ValidationException 422 when `version` is missing or malformed.
     * @throws ApiProblemException 409 VERSION_CONFLICT.
     * @throws LogicException When the route parameter is not a bound model.
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $record = $request->route($parameter);

        if (! $record instanceof Model) {
            throw new LogicException("version.token:{$parameter} needs the route parameter {{$parameter}} bound to a model.");
        }

        $validator = Validator::make($request->only('version'), ['version' => VersionToken::rules()]);

        if ($validator->fails()) {
            $this->recordRefusal($request, $record, ErrorCode::ValidationFailed, 'version_invalid');

            throw new ValidationException($validator);
        }

        if (! VersionToken::matches($record, $validator->validated()['version'])) {
            $this->recordRefusal($request, $record, ErrorCode::VersionConflict, 'stale_version');
        }

        VersionToken::assertCurrent($record, $validator->validated()['version']);

        $response = $next($request);

        // The routing pipeline has already turned the endpoint's exception into this response; it keeps the exception.
        $exception = $response->exception ?? null;

        if ($exception instanceof ApiProblemException && $exception->errorCode === ErrorCode::VersionConflict) {
            $this->recordRefusal($request, $record, ErrorCode::VersionConflict, 'stale_version');
        }

        return $response;
    }

    private function recordRefusal(Request $request, Model $record, ErrorCode $code, string $reason): void
    {
        $user = $request->user();

        $this->audit->rejectedAttempt(
            $request->method().' /'.$request->path(),
            $code,
            $reason,
            $user?->getAuthIdentifier(),
            $user?->getAttribute('username'),
            $record->getTable(),
            $record->getKey(),
        );
    }
}
