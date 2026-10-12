<x-layouts.app title="CVR" page-title="Settings" current-page="settings">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="SYSTEM" title="Settings" description="Update your account details and choose which campus updates you receive." />
        @if(session('status'))<div class="notice-panel" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="notice-panel" role="alert">Please review the highlighted account details.</div>@endif
        <form class="content-panel settings-form" action="{{ route('settings.update') }}" method="post">
            @csrf @method('PUT')
            <div class="content-panel-heading"><div><p class="eyebrow">ACCOUNT</p><h2>Profile and preferences</h2></div></div>
            <div class="filter-row">
                <label for="settings-name">Display name</label><input id="settings-name" name="name" value="{{ old('name', auth()->user()->name) }}" required autocomplete="name">
                <label for="settings-email">Email</label><input id="settings-email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required autocomplete="email">
                <label for="settings-course">Course</label><input id="settings-course" name="course" value="{{ old('course', auth()->user()->course) }}" autocomplete="organization-title">
                <label for="settings-contact">Contact number</label><input id="settings-contact" name="contact_number" value="{{ old('contact_number', auth()->user()->contact_number) }}" autocomplete="tel">
            </div>
            <fieldset class="settings-preferences"><legend>Change password</legend>
                <label for="current-password">Current password</label><input id="current-password" name="current_password" type="password" autocomplete="current-password">
                <label for="new-password">New password (12 characters minimum)</label><input id="new-password" name="new_password" type="password" minlength="12" autocomplete="new-password">
                <label for="new-password-confirmation">Confirm new password</label><input id="new-password-confirmation" name="new_password_confirmation" type="password" minlength="12" autocomplete="new-password">
            </fieldset>
            <fieldset class="settings-preferences"><legend>Notifications</legend>
                @foreach(['in_app_notifications' => 'In-app alerts', 'reservation_updates' => 'Reservation status updates', 'equipment_updates' => 'Equipment request updates'] as $key => $label)
                    <label><input type="hidden" name="preferences[{{ $key }}]" value="0"><input type="checkbox" name="preferences[{{ $key }}]" value="1" @checked(old('preferences.'.$key, auth()->user()->preferences[$key] ?? true))>{{ $label }}</label>
                @endforeach
            </fieldset>
            <label for="default-duration">Default reservation duration</label><select id="default-duration" name="preferences[default_duration_minutes]"><option value="60" @selected((int) old('preferences.default_duration_minutes', auth()->user()->preferences['default_duration_minutes'] ?? 120) === 60)>1 hour</option><option value="120" @selected((int) old('preferences.default_duration_minutes', auth()->user()->preferences['default_duration_minutes'] ?? 120) === 120)>2 hours</option><option value="240" @selected((int) old('preferences.default_duration_minutes', auth()->user()->preferences['default_duration_minutes'] ?? 120) === 240)>4 hours</option></select>
            <button class="primary-button" type="submit">Save settings</button>
        </form>
    </main>
</x-layouts.app>
