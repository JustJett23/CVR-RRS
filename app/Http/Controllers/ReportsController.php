<?php

namespace App\Http\Controllers;

use App\Models\EquipmentRequest;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->is_active && in_array($user->role, ['admin', 'creator'], true), 403);

        $filters = $request->validate([
            'start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        $endDate = Carbon::parse($filters['end_date'] ?? today()->toDateString())->endOfDay();
        $startDate = Carbon::parse($filters['start_date'] ?? $endDate->copy()->startOfMonth()->toDateString())->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'start_date' => 'The start date must be on or before the end date.',
            ]);
        }

        $reservationQuery = Reservation::query()
            ->whereBetween('starts_at', [$startDate, $endDate]);
        $approvedReservations = (clone $reservationQuery)->where('status', 'approved');
        $durationExpression = $this->durationInMinutesExpression('starts_at', 'ends_at');
        $bookedMinutes = (clone $approvedReservations)->sum(DB::raw($durationExpression));

        $roomUsage = DB::table('reservations')
            ->join('rooms', 'rooms.id', '=', 'reservations.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('reservations.status', 'approved')
            ->whereBetween('reservations.starts_at', [$startDate, $endDate])
            ->select([
                'rooms.id',
                'rooms.code',
                'rooms.name',
                'floors.name as floor_name',
                'buildings.name as building_name',
            ])
            ->selectRaw('COUNT(reservations.id) as reservations_count')
            ->selectRaw('SUM('.$this->durationInMinutesExpression('reservations.starts_at', 'reservations.ends_at').') as booked_minutes')
            ->groupBy('rooms.id', 'rooms.code', 'rooms.name', 'floors.name', 'buildings.name')
            ->orderByDesc('booked_minutes')
            ->orderBy('rooms.code')
            ->limit(10)
            ->get();

        $equipmentRequests = EquipmentRequest::query()
            ->whereHas('reservation', fn (EloquentBuilder $query) => $query->whereBetween('starts_at', [$startDate, $endDate]));

        $equipmentByStatus = (clone $equipmentRequests)
            ->select('status')
            ->selectRaw('COUNT(*) as requests_count')
            ->selectRaw('SUM(quantity) as units_requested')
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $equipmentBorrowedUnits = (clone $equipmentRequests)->where('status', 'released')->sum('quantity');

        $equipmentDemand = DB::table('equipment_requests')
            ->join('equipment', 'equipment.id', '=', 'equipment_requests.equipment_id')
            ->join('reservations', 'reservations.id', '=', 'equipment_requests.reservation_id')
            ->whereBetween('reservations.starts_at', [$startDate, $endDate])
            ->select(['equipment.id', 'equipment.name', 'equipment.category'])
            ->selectRaw('SUM(equipment_requests.quantity) as units_requested')
            ->selectRaw('COUNT(equipment_requests.id) as requests_count')
            ->groupBy('equipment.id', 'equipment.name', 'equipment.category')
            ->orderByDesc('units_requested')
            ->orderBy('equipment.name')
            ->limit(10)
            ->get();

        return view('pages.reports', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'metrics' => [
                'reservationCount' => (clone $reservationQuery)->count(),
                'approvedCount' => (clone $approvedReservations)->count(),
                'bookedHours' => number_format($bookedMinutes / 60, 1),
                'equipmentUnits' => (clone $equipmentRequests)->sum('quantity'),
                'equipmentBorrowedUnits' => $equipmentBorrowedUnits,
            ],
            'roomUsage' => $roomUsage,
            'equipmentByStatus' => $equipmentByStatus,
            'equipmentDemand' => $equipmentDemand,
        ]);
    }

    private function durationInMinutesExpression(string $startColumn, string $endColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn})) / 60",
            'sqlite' => "(julianday({$endColumn}) - julianday({$startColumn})) * 1440",
            default => "TIMESTAMPDIFF(MINUTE, {$startColumn}, {$endColumn})",
        };
    }
}
