<?php

namespace App\Modules\Shared\Errors;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception on /api/* as an RFC 9457 problem body with a stable code (CT-01).
 *
 * Members: type, title, status, code, message_key, detail; "errors" on 422; extension members from
 * ApiProblemException. The detail is the catalogue text (or the thrower's override), never the
 * exception message, so no class name, SQL, file or trace reaches the client, even in debug mode
 * (NFR-UX-4). Reporting to the log is not affected.
 */
class ProblemRenderer
{
    public const string CONTENT_TYPE = 'application/problem+json';

    /**
     * Framework HTTP statuses that have a catalogue code. Any other 4xx is BAD_REQUEST, any other 5xx SERVER_ERROR.
     *
     * @var array<int, ErrorCode>
     */
    private const array CODE_BY_STATUS = [
        401 => ErrorCode::Unauthenticated,
        403 => ErrorCode::Forbidden,
        404 => ErrorCode::NotFound,
        405 => ErrorCode::MethodNotAllowed,
        429 => ErrorCode::TooManyAttempts,
        503 => ErrorCode::ServiceUnavailable,
    ];

    /**
     * Laravel's own rule objects; their failures keep a "validation.<rule>" key like string rules.
     */
    private const string FRAMEWORK_RULE_NAMESPACE = 'Illuminate\\Validation\\Rules\\';

    /**
     * The problem response for an API request, or null to leave the exception to Laravel's default rendering.
     */
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof ApiProblemException => $this->problem(
                $exception->errorCode,
                $exception->detail ?? __($exception->errorCode->messageKey(), $exception->replace),
                $exception->extensions,
                $exception->getHeaders(),
            ),
            $exception instanceof ValidationException => $this->validationProblem($exception),
            $exception instanceof AuthenticationException => $this->problem(ErrorCode::Unauthenticated),
            $exception instanceof HttpExceptionInterface => $this->problem(
                $this->codeForStatus($exception->getStatusCode()),
                headers: $exception->getHeaders(),
            ),
            default => $this->problem(ErrorCode::ServerError),
        };
    }

    /**
     * @param  array<string, mixed>  $members  Extension members appended after the core members
     * @param  array<string, string>  $headers
     */
    public function problem(ErrorCode $code, ?string $detail = null, array $members = [], array $headers = []): JsonResponse
    {
        $body = [
            'type' => $code->type(),
            'title' => $code->title(),
            'status' => $code->status(),
            'code' => $code->value,
            'message_key' => $code->messageKey(),
            'detail' => $detail ?? __($code->messageKey()),
            ...$members,
        ];

        return new JsonResponse(
            $body,
            $code->status(),
            ['Content-Type' => self::CONTENT_TYPE, ...$headers],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function codeForStatus(int $status): ErrorCode
    {
        return self::CODE_BY_STATUS[$status] ?? ($status >= 500 ? ErrorCode::ServerError : ErrorCode::BadRequest);
    }

    /**
     * 422 with "errors": { field: [message keys] } (CT-01, P1-A2 question 10) and the first English message as detail.
     */
    private function validationProblem(ValidationException $exception): JsonResponse
    {
        $messages = $exception->validator->errors()->messages();
        $failedRules = $exception->validator->failed();

        $errors = [];

        foreach ($messages as $field => $fieldMessages) {
            $keys = isset($failedRules[$field])
                ? array_map($this->ruleMessageKey(...), array_keys($failedRules[$field]))
                : array_map($this->messageAsKey(...), $fieldMessages);

            $errors[$field] = array_values(array_unique($keys));
        }

        $firstMessage = $exception->validator->errors()->first();
        $detail = $firstMessage !== '' && Lang::has($firstMessage) ? __($firstMessage) : $firstMessage;

        return $this->problem(
            ErrorCode::ValidationFailed,
            $detail !== '' ? $detail : null,
            ['errors' => $errors],
        );
    }

    /**
     * "Required" → "validation.required"; Laravel rule objects (Enum, File, Password) likewise; other rule objects
     * and closures → "errors.validation_failed" until custom rules carry their own key.
     */
    private function ruleMessageKey(string $rule): string
    {
        if (! str_contains($rule, '\\')) {
            return 'validation.'.Str::snake($rule);
        }

        if (str_starts_with($rule, self::FRAMEWORK_RULE_NAMESPACE)) {
            return 'validation.'.Str::snake(class_basename($rule));
        }

        return ErrorCode::ValidationFailed->messageKey();
    }

    /**
     * A message given by ValidationException::withMessages(): kept when it is a translation key, otherwise the generic key.
     */
    private function messageAsKey(string $message): string
    {
        return Lang::has($message) ? $message : ErrorCode::ValidationFailed->messageKey();
    }
}
