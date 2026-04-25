<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'title',
        'is_group',
        'subject',
        'description',
        'max_students',
        'teacher_id',
        'invite_code',
    ];

    protected $casts = [
        'is_group'     => 'boolean',
        'max_students' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function classMembers(): HasMany
    {
        return $this->hasMany(ClassMember::class);
    }

    public function activeClassMembers(): HasMany
    {
        return $this->hasMany(ClassMember::class)->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function pinnedAnnouncement(): HasOne
    {
        return $this->hasOne(Message::class)->where('type', 'announcement')->latestOfMany();
    }

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function studentCount(): int
    {
        return $this->activeClassMembers()->where('role', 'student')->count();
    }

    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}

