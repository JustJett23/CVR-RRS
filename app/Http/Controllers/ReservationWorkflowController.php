<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PreferenceNotifier;
use App\Services\ReservationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationWorkflowController extends Controller
{
    public function approve(Request $request, Reservation $reservation, ReservationWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $reviewer = $request->user();
        abort_unless($this->isStaff($reviewer), 403);

        $reservation = $service->approve($reservation, $reviewer);
        $activityLogger->record($reviewer, 'reservation.approved', $reservation, ipAddress: $request->ip());
        $notifier->notify($reservation->user, 'reservation_notifications', 'reservation_status', 'Reservation approved', 'Your reservation request for '.$reservation->room->name.' was approved.', ['reservation_id' => $reservation->id]);

        return to_route('reservations')->with('status', 'Reservation approved.');
    }

    public function reject(Request $request, Reservation $reservation, ReservationWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        abort_unless($this->isStaff($request->user()), 403);

        $reservation = $service->reject($reservation);
        $activityLogger->record($request->user(), 'reservation.rejected', $reservation, ipAddress: $request->ip());
        $notifier->notify($reservation->user, 'reservation_notifications', 'reservation_status', 'Reservation declined', 'Your reservation request for '.$reservation->room->name.' was declined.', ['reservation_id' => $reservation->id]);

        return to_route('reservations')->with('status', 'Reservation declined.');
    }

    public function cancel(Request $request, Reservation $reservation, ReservationWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $requester = $request->user();
        abort_unless($requester instanceof User && $requester->is_active, 403);

        $reservation = $service->cancel($reservation, $requester);
        $activityLogger->record($requester, 'reservation.cancelled', $reservation, ipAddress: $request->ip());
        $notifier->notify($requester, 'reservation_notifications', 'reservation_status', 'Reservation cancelled', 'Your reservation request for '.$reservation->room->name.' was cancelled.', ['reservation_id' => $reservation->id]);

        return to_route('reservations')->with('status', 'Pending reservation cancelled.');
    }

    private function isStaff(?User $user): bool
    {
        return $user instanceof User && $user->is_active && in_array($user->role, ['admin', 'creator'], true);
    }
}
