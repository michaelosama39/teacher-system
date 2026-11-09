<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Support\Phone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'password', 'is_active', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * Spatie permissions are always resolved against the "web" guard,
     * whatever guard (session or Sanctum token) authenticated the request.
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Phones are always stored normalized (01XXXXXXXXX).
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => Phone::normalize($value),
        );
    }

    /**
     * The student profile of a student account.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Students linked to this parent account.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot('relation')
            ->withTimestamps();
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(Role::Teacher);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([Role::Teacher, Role::Assistant]);
    }

    public function isParentOf(Student $student): bool
    {
        return $this->children()->whereKey($student->getKey())->exists();
    }

    /**
     * Effective permission names (the teacher implicitly has all of them).
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isTeacher()) {
            return Permission::values();
        }

        return $this->getAllPermissions()->pluck('name')->sort()->values()->all();
    }

    /**
     * Revoke every API token (optionally keeping one).
     */
    public function revokeTokens(?int $exceptTokenId = null): void
    {
        $this->tokens()
            ->when($exceptTokenId, fn ($query) => $query->whereKeyNot($exceptTokenId))
            ->delete();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'phone', 'is_active', 'must_change_password'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
