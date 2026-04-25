<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'warnings_count',
        'last_login_ip',
        'city',
        'two_factor_secret',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'deleted_at'         => 'datetime',
        'password'           => 'hashed',
        'two_factor_secret'  => 'encrypted',
        'two_factor_enabled' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the teacher profile associated with this user.
     */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /**
     * Get the student profile associated with this user.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Teachers saved by this student.
     */
    public function savedTeachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_teachers', 'student_id', 'teacher_id')
            ->withTimestamps();
    }

    /**
     * Students who have saved this teacher.
     */
    public function savedByStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_teachers', 'teacher_id', 'student_id')
            ->withTimestamps();
    }

    /**
     * Conversations this user is part of.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Messages sent by this user.
     */
    public function messagesSent(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Bookings where this user is the student.
     */
    public function bookingsAsStudent(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    /**
     * Bookings where this user is the teacher.
     */
    public function bookingsAsTeacher(): HasMany
    {
        return $this->hasMany(Booking::class, 'teacher_id');
    }

    /**
     * Teacher availability records.
     */
    public function teacherAvailability(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_id');
    }

    /**
     * Teacher earnings.
     */
    public function teacherEarnings(): HasMany
    {
        return $this->hasMany(TeacherEarning::class, 'teacher_id');
    }

    /**
     * Notification preferences.
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    /**
     * Reviews written by this user.
     */
    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Reviews received by this user.
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * Normalize local storage avatar URLs so they remain valid across host/port changes.
     */
    public function getAvatarAttribute($value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/')) {
            return $path;
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Determine whether the user has the teacher role.
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Determine whether the user has the student role.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Determine whether the user has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
