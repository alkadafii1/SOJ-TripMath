<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    public static function record(
        string $entity,
        ?int $entityId,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'entity' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason,
        ]);
    }
}