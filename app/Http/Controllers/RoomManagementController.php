<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoomManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            in_array(Auth::user()->role, ['admin', 'creator'], true),
            403
        );

        $validated = $request->validate([
            'floor_id' => ['required', 'integer', 'exists:floors,id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:48'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['name'] = trim($validated['name']);

        $duplicate = Room::where('floor_id', $validated['floor_id'])
            ->where('code', $validated['code'])
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'This room code already exists on this floor.']);
        }

        Room::create($validated);

        return redirect()
            ->route('rooms')
            ->with('room_created', 'Room added successfully.');
    }
}
