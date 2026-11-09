<?php

namespace App\Actions\Students;

use App\Enums\ParentRelation;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class CreateStudentAction
{
    public function __construct(private LinkStudentAccountsAction $linkAccounts) {}

    /**
     * Create a student (by staff) with their student/parent accounts, atomically.
     *
     * @param  array{name: string, phone?: string|null, parent_phone?: string|null, parent_name?: string|null, parent_relation?: string|null, notes?: string|null}  $data
     * @return array{student: Student, credentials: list<array<string, string|null>>}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $student = Student::create([
                'name' => $data['name'],
                'status' => StudentStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            $credentials = $this->linkAccounts->handle(
                $student,
                $data['phone'] ?? null,
                $data['parent_phone'] ?? null,
                $data['parent_name'] ?? null,
                ParentRelation::tryFrom((string) ($data['parent_relation'] ?? '')),
            );

            return [
                'student' => $student->load(['user', 'parents']),
                'credentials' => $credentials,
            ];
        });
    }
}
