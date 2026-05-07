<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'channel',
        'audience',
        'type',
        'title',
        'message',
        'data',
        'is_critical',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_critical' => 'boolean',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
