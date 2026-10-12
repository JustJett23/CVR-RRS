<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightsController extends Controller
{
    public function reports(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'creator'], true), 403);

        $days = (int) $request->integer('days', 30);
        abort_if(! in_array($days, [7, 30, 90, 365], true), 422);
        $since = now()->subDays($days)->startOfDay();
        $reservations = Reservation::query()->where('reservations.created_at', '>=', $since);
        $equipmentRequests = EquipmentRequest::query()->where('equipment_requests.created_at', '>=', $since);
        $reservationDetails = (clone $reservations)->get(['starts_at', 'organization_name']);

        return view('pages.reports', [
            'days' => $days,
            'totals' => [
                'rooms' => Room::query()->count(),
                'reservations' => (clone $reservations)->count(),
                'approved' => (clone $reservations)->where('status', 'approved')->count(),
                'pending' => (clone $reservations)->where('status', 'pending')->count(),
                'cancelled' => (clone $reservations)->where('status', 'cancelled')->count(),
                'equipment_requests' => (clone $equipmentRequests)->count(),
            ],
            'byStatus' => (clone $reservations)->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderBy('status')->get(),
            'byRoom' => (clone $reservations)->join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->where('reservations.created_at', '>=', $since)
                ->selectRaw('rooms.name as room_name, COUNT(*) as total')->groupBy('rooms.name')->orderByDesc('total')->limit(8)->get(),
            'byMonth' => (clone $reservations)->get(['starts_at'])
                ->groupBy(fn (Reservation $reservation): string => $reservation->starts_at->format('Y-m'))
                ->map(fn ($items, string $month): array => ['month' => $month, 'total' => $items->count()])
                ->sortKeys(),
            'peakTimes' => $reservationDetails->groupBy(fn (Reservation $reservation): string => $reservation->starts_at->format('g A'))
                ->map(fn ($items, string $hour): array => ['hour' => $hour, 'total' => $items->count()])
                ->sortByDesc('total')->take(6),
            'byOrganization' => $reservationDetails->filter(fn (Reservation $reservation): bool => filled($reservation->organization_name))
                ->groupBy('organization_name')
                ->map(fn ($items, string $organization): array => ['organization' => $organization, 'total' => $items->count()])
                ->sortByDesc('total')->take(8),
            'byEquipment' => (clone $equipmentRequests)->where('equipment_requests.created_at', '>=', $since)
                ->join('equipment', 'equipment_requests.equipment_id', '=', 'equipment.id')
                ->selectRaw('equipment.name as equipment_name, SUM(equipment_requests.quantity) as total')
                ->groupBy('equipment.name')->orderByDesc('total')->limit(8)->get(),
        ]);
    }

    public function activityLogs(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'creator'], true), 403);

        return view('pages.activity-logs', [
            'activities' => ActivityLog::query()->with(['user:id,name,student_number', 'subject'])
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }
}
