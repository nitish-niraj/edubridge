<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'admin_id'); }
}
