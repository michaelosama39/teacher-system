<?php

namespace App\Http\Requests\Students;

/**
 * Same fields and rules as staff creation, but public.
 */
class RegisterStudentRequest extends StoreStudentRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
