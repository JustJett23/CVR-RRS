
@if (in_array(auth()->user()->role, ['admin', 'creator'], true))
<section class="content-panel">
    <div class="content-panel-heading">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h2>Add Rooms</h2>
        </div>
    </div>

    @if (session('room_created'))
        <p role="status">{{ session('room_created') }}</p>
    @endif

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('rooms.store') }}" class="stack-form">
        @csrf

        <div class="form-grid-two">
            <label class="filter-field">
                <span>Floor</span>
                <select name="floor_id" required>
                    <option value="">Select a floor</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor->id }}"
                            @selected(old('floor_id') == $floor->id)>
                            {{ $floor->building->name }} - {{ $floor->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="filter-field">
                <span>Room Code</span>
                <input name="code" maxlength="32"
                    value="{{ old('code') }}" required>
            </label>

            <label class="filter-field">
                <span>Room Name</span>
                <input name="name" maxlength="255"
                    value="{{ old('name') }}" required>
            </label>

            <label class="filter-field">
                <span>Room Type</span>
                <input name="type" maxlength="48"
                    value="{{ old('type') }}">
            </label>

            <label class="filter-field">
                <span>Capacity</span>
                <input name="capacity" type="number" min="0"
                    max="100000" value="{{ old('capacity', 0) }}" required>
            </label>

            <label class="filter-field">
                <span>Status</span>
                <select name="status" required>
                    <option value="active"
                        @selected(old('status', 'active') === 'active')>
                        Active
                    </option>
                    <option value="inactive"
                        @selected(old('status') === 'inactive')>
                        Inactive
                    </option>
                </select>
            </label>
        </div>

        <label class="filter-field">
            <span>Description (optional)</span>
            <textarea name="description" rows="2"
                maxlength="2000">{{ old('description') }}</textarea>
        </label>

        <div class="reservation-actions">
            <button class="primary-button" type="submit">
                Add Room
            </button>
        </div>
    </form>
</section>
@endif
