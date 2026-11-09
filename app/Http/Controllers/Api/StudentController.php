<?php

namespace App\Http\Controllers\Api;

use App\Actions\Students\ApproveStudentAction;
use App\Actions\Students\CreateStudentAction;
use App\Actions\Students\RejectStudentAction;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\RejectStudentRequest;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Student::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $students = Student::query()
            ->with(['user', 'parents'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $phone = Phone::normalizeOrNull($search);

                $query->where(function ($query) use ($search, $phone): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', $search);

                    if ($phone !== null) {
                        $query->orWhereHas('user', fn ($q) => $q->where('phone', $phone))
                            ->orWhereHas('parents', fn ($q) => $q->where('phone', $phone));
                    }
                });
            })
            ->latest('id')
            ->paginate(25);

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request, CreateStudentAction $createStudent): JsonResponse
    {
        ['student' => $student, 'credentials' => $credentials] = $createStudent->handle($request->validated());

        return response()->json([
            'data' => StudentResource::make($student),
            'credentials' => $credentials,
        ], 201);
    }

    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return StudentResource::make($student->load(['user', 'parents']));
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return StudentResource::make($student->load(['user', 'parents']));
    }

    public function regenerateQr(Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        return response()->json(['data' => ['qr_token' => $student->regenerateQrToken()]]);
    }

    public function approve(Student $student, ApproveStudentAction $approveStudent): JsonResponse
    {
        Gate::authorize('approve', $student);

        ['student' => $student, 'credentials' => $credentials] = $approveStudent->handle($student);

        return response()->json([
            'data' => StudentResource::make($student),
            'credentials' => $credentials,
        ]);
    }

    public function reject(RejectStudentRequest $request, Student $student, RejectStudentAction $rejectStudent): JsonResponse
    {
        $rejectStudent->handle($student, $request->validated('reason'));

        return response()->json(['message' => 'تم رفض طلب التسجيل.']);
    }
}
