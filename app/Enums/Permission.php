<?php

namespace App\Enums;

/**
 * Permissions the teacher can grant to assistants.
 *
 * The teacher implicitly has every permission (Gate::before).
 * Students and parents are authorized through policies, never through these.
 */
enum Permission: string
{
    case AttendanceScan = 'attendance.scan';
    case AttendanceView = 'attendance.view';
    case StudentsView = 'students.view';
    case StudentsCreate = 'students.create';
    case StudentsEdit = 'students.edit';
    case StudentsApprove = 'students.approve';
    case PaymentsCollect = 'payments.collect';
    case PaymentsReports = 'payments.reports';
    case GroupsManage = 'groups.manage';

    public function label(): string
    {
        return match ($this) {
            self::AttendanceScan => 'تسجيل الحضور (مسح QR)',
            self::AttendanceView => 'عرض الحضور',
            self::StudentsView => 'عرض الطلاب',
            self::StudentsCreate => 'إضافة طلاب',
            self::StudentsEdit => 'تعديل بيانات الطلاب',
            self::StudentsApprove => 'قبول / رفض طلبات التسجيل',
            self::PaymentsCollect => 'تحصيل المدفوعات',
            self::PaymentsReports => 'تقارير المدفوعات',
            self::GroupsManage => 'إدارة المجموعات',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $permission): array => ['value' => $permission->value, 'label' => $permission->label()],
            self::cases(),
        );
    }
}
