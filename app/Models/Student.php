<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['user_id', 'name', 'status', 'notes', 'registration_data'])]
#[Hidden(['qr_token', 'registration_data'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const QR_TOKEN_LENGTH = 48;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'registration_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            $student->qr_token ??= static::newQrToken();
        });

        /*
         * The code is derived from the auto-increment id, so it is generated
         * after insert: unique by construction, no race between concurrent inserts.
         */
        static::created(function (Student $student): void {
            if ($student->code === null) {
                $student->code = static::codeFor($student->getKey());
                $student->saveQuietly();
            }
        });
    }

    public static function codeFor(int $id): string
    {
        return config('teacher.student_code_prefix').'-'.(1000 + $id);
    }

    public static function newQrToken(): string
    {
        do {
            $token = Str::random(self::QR_TOKEN_LENGTH);
        } while (static::withTrashed()->where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Invalidate the printed QR card and issue a new token.
     */
    public function regenerateQrToken(): string
    {
        $this->qr_token = static::newQrToken();
        $this->save();

        return $this->qr_token;
    }

    /**
     * The student's own login account (only when they have their own phone).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relation')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeStatus(Builder $query, StudentStatus $status): void
    {
        $query->where('status', $status);
    }

    public function isPending(): bool
    {
        return $this->status === StudentStatus::Pending;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('students')
            ->logOnly(['name', 'code', 'status', 'user_id', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
