<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

trait LogsSoftDeleteActivity
{
    protected static function bootLogsSoftDeleteActivity(): void
    {
        static::deleted(static function (Model $model): void {
            if ($model->trashed() && ! $model->isForceDeleting()) {
                $model->recordSoftDeleteActivity('soft_deleted');
            }
        });

        static::restored(static function (Model $model): void {
            $model->recordSoftDeleteActivity('restored');
        });

        static::forceDeleted(static function (Model $model): void {
            $model->recordSoftDeleteActivity('force_deleted');
        });
    }

    private function recordSoftDeleteActivity(string $action): void
    {
        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'metadata' => ['model' => class_basename($this)],
            'ip_address' => request()->ip(),
        ]);
    }
}
