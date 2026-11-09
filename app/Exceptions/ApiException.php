<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A domain error rendered as {message, code, errors}.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function passwordChangeRequired(): self
    {
        return new self('يجب تغيير كلمة المرور قبل المتابعة.', 'PASSWORD_CHANGE_REQUIRED', 403);
    }

    public static function accountInactive(): self
    {
        return new self('هذا الحساب موقوف. تواصل مع المدرس.', 'ACCOUNT_INACTIVE', 403);
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'بيانات الدخول غير صحيحة.',
            'INVALID_CREDENTIALS',
            422,
            ['identifier' => ['بيانات الدخول غير صحيحة.']],
        );
    }

    public static function featureDisabled(): self
    {
        return new self('هذه الخدمة غير متاحة.', 'FEATURE_DISABLED', 404);
    }

    public static function studentNotPending(): self
    {
        return new self('هذا الطالب ليس في انتظار الموافقة.', 'STUDENT_NOT_PENDING', 409);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'errors' => (object) $this->errors,
        ], $this->status);
    }
}
