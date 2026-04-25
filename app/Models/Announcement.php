<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'message', 'target_role', 'delivery_type',
        'is_active', 'starts_at', 'ends_at', 'created_by', 'sent_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActiveForRole($query, string $role)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($role) {
                $q->where('target_role', 'all')->orWhere('target_role', $role);
            })
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
