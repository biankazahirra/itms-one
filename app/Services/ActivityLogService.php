<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use LogicException;

class ActivityLogService
{
    /**
     * Record one user activity entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $entityType,
        int $entityId,
        string $action,
        string $description,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= auth()->user();

        if (! $actor instanceof User) {
            throw new LogicException('An authenticated user is required to record activity.');
        }

        return ActivityLog::create([
            'user_id' => $actor->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
