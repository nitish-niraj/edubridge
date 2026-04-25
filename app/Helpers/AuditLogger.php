<?php

namespace App\Helpers;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(string $action, string $entityType, int $entityId, array $details = []): void
    {
        AuditLog::create([
            'admin_id'    => auth()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
        ]);
    }
}
