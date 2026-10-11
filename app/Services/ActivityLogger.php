<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(?User $actor, string $action, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => app()->bound('request') ? app(Request::class)->ip() : null,
        ]);
    }
}
