<?php

namespace App\Actions\Students;

use App\Enums\ParentRelation;
use App\Enums\StudentStatus;
use App\Exceptions\ApiException;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class ApproveStudentAction
{
    public function __construct(private LinkStudentAccountsAction $linkAccounts) {}

    /**
     * Activate a pending student and create/link their accounts.
     *
     * @return array{student: Student, credentials: list<array<string, string|null>>}
     */
    public function handle(Student $student): array
    {
        if (! $student->isPending()) {
            throw ApiException::studentNotPending();
        }

        return DB::transaction(function () use ($student): array {
            $data = $student->registration_data ?? [];

            $credentials = $this->linkAccounts->handle(
                $student,
                $data['phone'] ?? null,
                $data['parent_phone'] ?? null,
                $data['parent_name'] ?? null,
                ParentRelation::tryFrom((string) ($data['parent_relation'] ?? '')),
            );

            $student->update([
                'status' => StudentStatus::Active,
                'registration_data' => null,
            ]);

            return [
                'student' => $student->load(['user', 'parents']),
                'credentials' => $credentials,
            ];
        });
    }
}
