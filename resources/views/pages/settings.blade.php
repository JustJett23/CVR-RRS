<x-layouts.app title="CVR" page-title="Settings" current-page="settings">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="ACCOUNT" title="Profile & Settings" :description="$isStudent ? 'Manage your campus profile and account security.' : 'Manage your campus profile, account security, and reservation defaults.'" />

        @if (session('status'))
            <div class="notice-panel reservation-feedback" role="status">{{ session('status') }}</div>
        @endif

        @php($identityLocked = $profile->onboarding_verified_at !== null)
        <section class="settings-form-grid" aria-label="Account settings">
            <section class="content-panel" aria-labelledby="profile-settings-title">
                <div class="content-panel-heading"><div><p class="eyebrow">PERSONAL DETAILS</p><h2 id="profile-settings-title">Profile</h2></div></div>
                <form method="POST" action="{{ route('settings.profile.update') }}" class="stack-form settings-form" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @if ($identityLocked)
                        <label class="filter-field"><span>Full name <small>Verified from your NPC ID</small></span><input name="name" value="{{ $profile->name }}" maxlength="150" autocomplete="name" readonly aria-describedby="identity-lock-note"></label>
                        <label class="filter-field"><span>Course <small>Verified from your NPC ID</small></span><input name="course" value="{{ $profile->course }}" maxlength="150" autocomplete="organization-title" readonly aria-describedby="identity-lock-note"></label>
                        <p class="settings-help identity-lock-note" id="identity-lock-note">Name and course stay linked to the details verified from your scanned campus ID.</p>
                    @else
                        <label class="filter-field"><span>Full name</span><input name="name" value="{{ old('name', $profile->name) }}" maxlength="150" autocomplete="name" required></label>
                        <label class="filter-field"><span>Course</span><input name="course" value="{{ old('course', $profile->course) }}" maxlength="150" autocomplete="organization-title"></label>
                    @endif
                    <label class="filter-field"><span>Contact number</span><input name="contact_number" value="{{ old('contact_number', $profile->contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel"></label>
                    <label class="filter-field"><span>Guardian full name</span><input name="guardian_name" value="{{ old('guardian_name', $profile->emergency_contact_name ?: $profile->emergency_contact) }}" maxlength="150" autocomplete="name"></label>
                    <label class="filter-field"><span>Guardian mobile number</span><input name="guardian_phone" value="{{ old('guardian_phone', $profile->emergency_contact_phone) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel"></label>
                    <label class="filter-field"><span>Profile photo</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small>Shown on your profile and in the sidebar. JPEG, PNG, or WebP up to 2 MB.</small></label>
                    <div class="settings-readonly"><span>Institutional email</span><strong>{{ $profile->email }}</strong><span>Student number</span><strong>{{ $profile->student_number ?: 'Not provided' }}</strong><span>Organization ID</span><strong>{{ $profile->organization_id ?: 'Not assigned' }}</strong></div>
                    <button class="primary-button" type="submit">Save profile</button>
                </form>
            </section>

            <section class="content-panel" aria-labelledby="security-settings-title">
                <div class="content-panel-heading"><div><p class="eyebrow">ACCOUNT ACCESS</p><h2 id="security-settings-title">Change password</h2></div></div>
                <form method="POST" action="{{ route('settings.password.update') }}" class="stack-form settings-form">
                    @csrf
                    @method('PUT')
                    <label class="filter-field"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                    <label class="filter-field"><span>New password</span><input type="password" name="password" autocomplete="new-password" minlength="12" required><small>Use at least 12 characters.</small></label>
                    <label class="filter-field"><span>Confirm new password</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
                    <button class="primary-button" type="submit">Update password</button>
                </form>
            </section>

            @unless ($isStudent)
            <section class="content-panel settings-preferences-panel" aria-labelledby="reservation-preferences-title">
                <div class="content-panel-heading"><div><p class="eyebrow">BOOKING DEFAULTS</p><h2 id="reservation-preferences-title">Reservation preferences</h2></div></div>
                <form method="POST" action="{{ route('settings.preferences.update') }}" class="stack-form settings-form">
                    @csrf
                    @method('PUT')
                    <label class="filter-field"><span>Default reservation length</span><select name="default_reservation_duration" required>@foreach ([60 => '1 hour', 120 => '2 hours', 180 => '3 hours', 240 => '4 hours'] as $duration => $label)<option value="{{ $duration }}" @selected((int) old('default_reservation_duration', $preferences['default_reservation_duration']) === $duration)>{{ $label }}</option>@endforeach</select></label>
                    <label class="filter-field"><span>Default organization</span><input name="default_organization_name" value="{{ old('default_organization_name', $preferences['default_organization_name']) }}" maxlength="255" placeholder="Optional organization name"></label>
                    <div class="settings-checkboxes">
                        <label><input type="hidden" name="reservation_notifications" value="0"><input type="checkbox" name="reservation_notifications" value="1" @checked((bool) old('reservation_notifications', $preferences['reservation_notifications']))> Reservation status alerts</label>
                        <label><input type="hidden" name="equipment_notifications" value="0"><input type="checkbox" name="equipment_notifications" value="1" @checked((bool) old('equipment_notifications', $preferences['equipment_notifications']))> Equipment request alerts</label>
                    </div>
                    <p class="settings-help">Defaults prefill reservations. Alerts appear in the in-app notification menu and can be changed here.</p>
                    <button class="primary-button" type="submit">Save preferences</button>
                </form>
            </section>
            @endunless
        </section>

        @if ($errors->any())
            <div class="notice-panel reservation-feedback" role="alert">{{ $errors->first() }}</div>
        @endif
    </main>
</x-layouts.app>
