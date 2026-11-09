<?php

namespace App\Actions\Students;

use App\Exceptions\ApiException;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class RejectStudentAction
{
    /**
     * Reject a pending registration (soft-deleted, kept for the audit trail).
     */
    public function handle(Student $student, ?string $reason = null): void
    {
        if (! $student->isPending()) {
            throw ApiException::studentNotPending();
        }

        DB::transaction(function () use ($student, $reason): void {
            activity('students')
                ->performedOn($student)
                ->event('rejected')
                ->withProperties(['reason' => $reason])
                ->log('rejected');

            $student->delete();
        });
    }
}
