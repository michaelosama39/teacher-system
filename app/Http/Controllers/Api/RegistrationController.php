<?php

namespace App\Http\Controllers\Api;

use App\Actions\Students\RegisterStudentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\RegisterStudentRequest;
use Illuminate\Http\JsonResponse;

/**
 * Public self-registration (route middleware "feature:self_registration").
 */
class RegistrationController extends Controller
{
    public function store(RegisterStudentRequest $request, RegisterStudentAction $registerStudent): JsonResponse
    {
        $student = $registerStudent->handle($request->validated());

        return response()->json([
            'message' => 'تم استلام طلب التسجيل، وسيتم التواصل معك بعد مراجعته.',
            'data' => [
                'id' => $student->id,
                'code' => $student->code,
                'name' => $student->name,
                'status' => $student->status->value,
            ],
        ], 201);
    }
}
