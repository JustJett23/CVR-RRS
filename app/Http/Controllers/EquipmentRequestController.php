<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\EquipmentRequestWorkflowService;
use App\Services\PreferenceNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        $isStaff = in_array($user->role, ['admin', 'creator'], true);
        $equipment = Equipment::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'quantity', 'available_quantity', 'description']);
        $requestedEquipmentId = (int) ($request->old('equipment_id') ?: $request->integer('equipment_id'));

        return view('pages.equipment-requests', [
            'isStaff' => $isStaff,
            'requests' => EquipmentRequest::query()
                ->with(['equipment', 'reservation.room', 'requester'])
                ->when(! $isStaff, fn ($query) => $query->where('requested_by', $user->getKey()))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'reservations' => $isStaff ? collect() : Reservation::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'approved')
                ->where('ends_at', '>=', now())
                ->with('room:id,code,name')
                ->orderBy('starts_at')
                ->get(['id', 'room_id', 'starts_at', 'ends_at']),
            'equipment' => $equipment,
            'selectedEquipmentId' => $equipment->contains('id', $requestedEquipmentId) ? $requestedEquipmentId : null,
        ]);
    }

    public function store(Request $request, EquipmentRequestWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $requester = $request->user();
        abort_unless($requester instanceof User && $requester->is_active, 403);

        $data = $request->validate([
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $reservation = Reservation::query()->findOrFail($data['reservation_id']);
        $equipmentRequest = $service->create($requester, $reservation, $data);
        $activityLogger->record($requester, 'equipment_request.created', $equipmentRequest, ['quantity' => $equipmentRequest->quantity], $request->ip());
        $notifier->notify($requester, 'equipment_notifications', 'equipment_status', 'Equipment request submitted', 'Your request for '.$equipmentRequest->quantity.' unit(s) of equipment is awaiting review.', ['equipment_request_id' => $equipmentRequest->id]);

        return to_route('equipment-requests')->with('status', 'Equipment request submitted for review.');
    }

    public function approve(Request $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $staff = $this->staff($request->user());
        $equipmentRequest = $service->approve($equipmentRequest);
        $activityLogger->record($staff, 'equipment_request.approved', $equipmentRequest, ['quantity' => $equipmentRequest->quantity], $request->ip());
        $notifier->notify($equipmentRequest->requester, 'equipment_notifications', 'equipment_status', 'Equipment request approved', 'Your request for '.$equipmentRequest->equipment->name.' was approved.', ['equipment_request_id' => $equipmentRequest->id]);

        return to_route('equipment-requests')->with('status', 'Equipment request approved and stock reserved.');
    }

    public function reject(Request $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $staff = $this->staff($request->user());
        $equipmentRequest = $service->reject($equipmentRequest);
        $activityLogger->record($staff, 'equipment_request.rejected', $equipmentRequest, ipAddress: $request->ip());
        $notifier->notify($equipmentRequest->requester, 'equipment_notifications', 'equipment_status', 'Equipment request declined', 'Your request for '.$equipmentRequest->equipment->name.' was declined.', ['equipment_request_id' => $equipmentRequest->id]);

        return to_route('equipment-requests')->with('status', 'Equipment request rejected.');
    }

    public function release(Request $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $staff = $this->staff($request->user());
        $equipmentRequest = $service->release($equipmentRequest);
        $activityLogger->record($staff, 'equipment_request.released', $equipmentRequest, ipAddress: $request->ip());
        $notifier->notify($equipmentRequest->requester, 'equipment_notifications', 'equipment_status', 'Equipment ready for pickup', $equipmentRequest->equipment->name.' has been marked as released for your reservation.', ['equipment_request_id' => $equipmentRequest->id]);

        return to_route('equipment-requests')->with('status', 'Equipment marked as released.');
    }

    public function return(Request $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service, ActivityLogger $activityLogger, PreferenceNotifier $notifier): RedirectResponse
    {
        $staff = $this->staff($request->user());
        $equipmentRequest = $service->return($equipmentRequest);
        $activityLogger->record($staff, 'equipment_request.returned', $equipmentRequest, ['quantity' => $equipmentRequest->quantity], $request->ip());
        $notifier->notify($equipmentRequest->requester, 'equipment_notifications', 'equipment_status', 'Equipment return recorded', 'The return of '.$equipmentRequest->equipment->name.' was recorded.', ['equipment_request_id' => $equipmentRequest->id]);

        return to_route('equipment-requests')->with('status', 'Equipment return recorded and stock restored.');
    }

    private function staff(?User $user): User
    {
        abort_unless($user instanceof User && $user->is_active && in_array($user->role, ['admin', 'creator'], true), 403);

        return $user;
    }
}
