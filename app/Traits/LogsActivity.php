<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Helper untuk mencatat aktivitas pengguna ke tabel activity_logs.
 */
trait LogsActivity
{
    private function logActivity(Request $request, string $activity, object $entity, string $description): void
    {
        if (! session('user_id')) {
            return;
        }

        ActivityLog::create([
            'user_id' => session('user_id'),
            'activity' => $activity,
            'entity_type' => $entity::class,
            'entity_id' => (string) $entity->id,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
