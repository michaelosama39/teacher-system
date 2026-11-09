<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

/**
 * Staff are authorized by permissions; students and parents only by ownership.
 * (The teacher passes everything via Gate::before.)
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::StudentsView);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::StudentsView)
            || $student->user_id === $user->id
            || $user->isParentOf($student);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::StudentsCreate);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::StudentsEdit);
    }

    public function approve(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::StudentsApprove);
    }
}
