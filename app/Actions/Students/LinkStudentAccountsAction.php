<?php

namespace App\Actions\Students;

use App\Enums\ParentRelation;
use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Phone;
use App\Support\TempPassword;
use Illuminate\Validation\ValidationException;

/**
 * Creates/links the login accounts of a student. Must run inside a transaction.
 *
 * - Student account only when the student has their OWN phone.
 * - Parent: an existing parent with that phone is linked, otherwise a parent account is created.
 * - Student phone == parent phone: only the parent account is created/linked.
 */
class LinkStudentAccountsAction
{
    /**
     * Returns the credentials of NEW accounts only (existing parents are just linked).
     *
     * @return list<array{role: string, name: string, phone: string, password: string, code?: string|null}>
     */
    public function handle(
        Student $student,
        ?string $studentPhone,
        ?string $parentPhone,
        ?string $parentName = null,
        ?ParentRelation $relation = null,
    ): array {
        $studentPhone = Phone::normalizeOrNull($studentPhone);
        $parentPhone = Phone::normalizeOrNull($parentPhone);

        if ($studentPhone !== null && $studentPhone === $parentPhone) {
            $studentPhone = null;
        }

        $credentials = [];

        if ($studentPhone !== null) {
            $credentials[] = $this->createStudentAccount($student, $studentPhone);
        }

        if ($parentPhone !== null) {
            $credential = $this->linkParent($student, $parentPhone, $parentName, $relation);

            if ($credential !== null) {
                $credentials[] = $credential;
            }
        }

        return $credentials;
    }

    /**
     * @return array{role: string, name: string, phone: string, password: string, code: string|null}
     */
    private function createStudentAccount(Student $student, string $phone): array
    {
        if (User::withTrashed()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم موبايل الطالب مستخدم في حساب آخر.',
            ]);
        }

        $password = TempPassword::generate();

        $user = User::create([
            'name' => $student->name,
            'phone' => $phone,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $user->assignRole(Role::Student);

        $student->user()->associate($user)->save();

        return [
            'role' => Role::Student->value,
            'name' => $user->name,
            'phone' => $phone,
            'password' => $password,
            'code' => $student->code,
        ];
    }

    /**
     * @return array{role: string, name: string, phone: string, password: string}|null
     */
    private function linkParent(Student $student, string $phone, ?string $name, ?ParentRelation $relation): ?array
    {
        $parent = User::withTrashed()->where('phone', $phone)->lockForUpdate()->first();
        $credential = null;

        if ($parent !== null && ($parent->trashed() || ! $parent->hasRole(Role::Parent))) {
            throw ValidationException::withMessages([
                'parent_phone' => 'رقم موبايل ولي الأمر مسجل لحساب آخر ليس ولي أمر.',
            ]);
        }

        if ($parent === null) {
            $password = TempPassword::generate();

            $parent = User::create([
                'name' => $name ?: 'ولي أمر '.$student->name,
                'phone' => $phone,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $parent->assignRole(Role::Parent);

            $credential = [
                'role' => Role::Parent->value,
                'name' => $parent->name,
                'phone' => $phone,
                'password' => $password,
            ];
        }

        $student->parents()->syncWithoutDetaching([$parent->id => ['relation' => $relation?->value]]);

        activity('students')
            ->performedOn($student)
            ->event('parent_linked')
            ->withProperties(['parent_id' => $parent->id, 'new_account' => $credential !== null])
            ->log('parent_linked');

        return $credential;
    }
}
