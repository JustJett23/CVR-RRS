<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->is_active && in_array($user->role, ['admin', 'creator'], true), 403);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when(
                $filters['actor'] ?? null,
                fn (Builder $query, string $actor) => $query->whereHas(
                    'user',
                    fn (Builder $userQuery) => $userQuery->where('name', 'like', '%'.$actor.'%'),
                ),
            )
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $startDate) => $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay()),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $endDate) => $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay()),
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('pages.activity-logs', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
        ]);
    }
}
