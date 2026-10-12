<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelEquipmentRequestRecordRequest;
use App\Http\Requests\EquipmentRequestReviewRequest;
use App\Http\Requests\ReleaseEquipmentRequestRequest;
use App\Http\Requests\ReturnEquipmentRequestRequest;
use App\Http\Requests\StoreEquipmentRequestRecordRequest;
use App\Http\Requests\UpdateEquipmentRequestRecordRequest;
use App\Models\EquipmentRequest;
use App\Services\EquipmentRequestWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = EquipmentRequest::query()
            ->with([
                'equipment',
                'reservation.room.floor.building',
                'requester:id,name,student_number',
                'reviewer:id,name',
                'releaser:id,name',
                'returner:id,name',
            ])
            ->when(! in_array($request->user()?->role, ['admin', 'creator'], true), fn ($query) => $query->where('requested_by', $request->user()->getKey()))
            ->latest()
            ->paginate(20);

        return response()->json($requests);
    }

    public function show(Request $request, EquipmentRequest $equipmentRequest): JsonResponse
    {
        abort_unless(
            $equipmentRequest->requested_by === $request->user()->getKey()
                || in_array($request->user()->role, ['admin', 'creator'], true),
            403,
        );

        return response()->json([
            'data' => $equipmentRequest->load(['equipment', 'reservation.room.floor.building']),
        ]);
    }

    public function store(StoreEquipmentRequestRecordRequest $request, EquipmentRequestWorkflowService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->create($request->user(), $request->validated()),
        ], 201);
    }

    public function update(UpdateEquipmentRequestRecordRequest $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->update($request->user(), $equipmentRequest, $request->validated()),
        ]);
    }

    public function cancel(CancelEquipmentRequestRecordRequest $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->cancel($equipmentRequest, $request->validated('cancellation_reason')),
        ]);
    }

    public function review(EquipmentRequestReviewRequest $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service): JsonResponse
    {
        $decision = $request->validated();

        return response()->json([
            'data' => $service->review(
                $request->user(),
                $equipmentRequest,
                $decision['status'],
                $decision['decision_reason'] ?? null,
            ),
        ]);
    }

    public function release(ReleaseEquipmentRequestRequest $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->release($request->user(), $equipmentRequest),
        ]);
    }

    public function returnEquipment(ReturnEquipmentRequestRequest $request, EquipmentRequest $equipmentRequest, EquipmentRequestWorkflowService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->returnEquipment($request->user(), $equipmentRequest, $request->validated()),
        ]);
    }
}
