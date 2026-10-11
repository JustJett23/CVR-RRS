<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskHelpAssistantRequest;
use App\Models\Equipment;
use App\Models\Room;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class HelpAssistantController extends Controller
{
    public function ask(AskHelpAssistantRequest $request, ActivityLogger $activityLogger): JsonResponse
    {
        $question = Str::lower($request->validated('question'));
        $user = $request->user();
        $links = [];

        if (Str::contains($question, ['equipment', 'projector', 'microphone', 'speaker'])) {
            $available = Equipment::query()->where('status', 'active')->sum('available_quantity');
            $answer = 'There are '.$available.' active equipment units currently available in the live inventory. Browse the Equipment page to see item details. Equipment requests can be submitted for your upcoming approved room reservation.';
            $links = [['label' => 'Equipment inventory', 'url' => route('equipment')], ['label' => 'Equipment requests', 'url' => route('equipment-requests')]];
            $intent = 'equipment';
        } elseif (Str::contains($question, ['my reservation', 'my booking', 'my request', 'reservation status'])) {
            $counts = $user->reservations()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $answer = 'Your reservations: '.($counts['pending'] ?? 0).' pending, '.($counts['approved'] ?? 0).' approved, '.($counts['rejected'] ?? 0).' rejected, and '.($counts['cancelled'] ?? 0).' cancelled.';
            $links = [['label' => 'Reservations', 'url' => route('reservations')], ['label' => 'Schedule', 'url' => route('schedule')]];
            $intent = 'my_reservations';
        } elseif (Str::contains($question, ['available', 'availability', 'open room', 'free room'])) {
            $now = now();
            $available = Room::query()->where('status', 'active')
                ->whereDoesntHave('reservations', fn ($query) => $query->whereIn('status', ['pending', 'approved'])->where('starts_at', '<=', $now)->where('ends_at', '>', $now))
                ->whereDoesntHave('maintenancePeriods', fn ($query) => $query->whereIn('status', ['scheduled', 'active'])->where('starts_at', '<=', $now)->where('ends_at', '>', $now))
                ->count();
            $answer = $available.' rooms are currently free according to the database. This is a snapshot; the server rechecks room and maintenance conflicts when a reservation is submitted.';
            $links = [['label' => 'Room availability', 'url' => route('availability')], ['label' => 'Room directory', 'url' => route('rooms')]];
            $intent = 'availability';
        } elseif (Str::contains($question, ['reserve', 'reservation', 'book', 'schedule'])) {
            $answer = 'To request a room, open Reservations, choose an active room and a future start and end time, enter the purpose, and submit. Your request remains pending until a campus reviewer approves it. The server checks for overlapping reservations and maintenance periods.';
            $links = [['label' => 'Reservation instructions', 'url' => route('help.reservation-steps')], ['label' => 'Start a reservation', 'url' => route('reservations')]];
            $intent = 'reservation_help';
        } elseif (Str::contains($question, ['floor plan', 'building', 'floor layout', 'map'])) {
            $answer = 'The interactive campus floor plan shows room locations and current room status. Use the building and floor controls to narrow the view.';
            $links = [['label' => 'Open floor plan', 'url' => route('floor-plan')]];
            $intent = 'floor_plan';
        } else {
            $answer = 'I can answer from live campus records about room availability, your reservation status, and equipment inventory. You can also ask how to submit a reservation or use the floor plan.';
            $links = [['label' => 'Frequently asked questions', 'url' => route('help')], ['label' => 'Contact support', 'url' => route('help')]];
            $intent = 'general_help';
        }

        $activityLogger->record($user, 'help.assistant_asked', null, ['intent' => $intent]);

        return response()->json(['answer' => $answer, 'links' => $links]);
    }
}
