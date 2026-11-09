<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Models\Student;

/**
 * Public self-registration. Only the student record is created (status pending);
 * the submitted phones are kept in registration_data and accounts are created
 * or linked when staff approve the request, so an anonymous request can never
 * create logins or attach itself to an existing parent.
 */
class RegisterStudentAction
{
    /**
     * @param  array{name: string, phone?: string|null, parent_phone?: string|null, parent_name?: string|null, parent_relation?: string|null, notes?: string|null}  $data
     */
    public function handle(array $data): Student
    {
        $student = Student::create([
            'name' => $data['name'],
            'status' => StudentStatus::Pending,
            'notes' => $data['notes'] ?? null,
            'registration_data' => [
                'phone' => $data['phone'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'parent_name' => $data['parent_name'] ?? null,
                'parent_relation' => $data['parent_relation'] ?? null,
            ],
        ]);

        activity('students')
            ->performedOn($student)
            ->event('self_registered')
            ->log('self_registered');

        return $student;
    }
}
