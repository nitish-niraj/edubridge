<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'is_muted',
        'can_draw',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
        'is_muted'  => 'boolean',
        'can_draw'  => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }
}
