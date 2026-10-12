<?php

use App\Models\UserNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:prune {--days=180}', function (): void {
    $days = max(30, (int) $this->option('days'));
    $deleted = UserNotification::query()->where('created_at', '<', now()->subDays($days))->delete();
    $this->info("Deleted {$deleted} notification(s) older than {$days} days.");
})->purpose('Prune expired user notifications');

Schedule::command('notifications:prune')->daily();
