<x-layouts.app title="CVR" page-title="Profile" current-page="profile">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="ACCOUNT" title="My profile" description="Review your campus identity and update the personal details linked to your account." action-label="Edit settings" :action-href="route('settings')" />

        @if (session('status'))
            <div class="notice-panel notice-panel-success reservation-feedback" role="status"><x-icon name="check" />{{ session('status') }}</div>
        @endif

        <div class="profile-page-grid">
            <section class="content-panel profile-summary-panel" aria-labelledby="profile-summary-title">
                <x-account-avatar class="profile-page-avatar" :name="$profile->name" />
                <p class="eyebrow">{{ strtoupper($profile->role) }} ACCOUNT</p>
                <h2 id="profile-summary-title">{{ $profile->name }}</h2>
                <p class="profile-email">{{ $profile->email }}</p>
                <dl class="profile-facts">
                    <div><dt>Course / program</dt><dd>{{ $profile->course ?: 'Not provided' }}</dd></div>
                    <div><dt>Student number</dt><dd>{{ $profile->student_number ?: 'Not assigned' }}</dd></div>
                    <div><dt>Organization ID</dt><dd>{{ $profile->organization_id ?: 'Not assigned' }}</dd></div>
                    <div><dt>Contact number</dt><dd>{{ $profile->contact_number ?: 'Not provided' }}</dd></div>
                    <div><dt>Guardian full name</dt><dd>{{ $profile->emergency_contact_name ?: $profile->emergency_contact ?: 'Not provided' }}</dd></div>
                    <div><dt>Guardian mobile number</dt><dd>{{ $profile->emergency_contact_phone ?: 'Not provided' }}</dd></div>
                </dl>
                <a class="primary-button" href="{{ route('settings') }}">Edit profile details</a>
            </section>

            <section class="content-panel profile-qr-panel" aria-labelledby="profile-qr-title">
                <div class="content-panel-heading"><div><p class="eyebrow">CAMPUS ACCESS</p><h2 id="profile-qr-title">Profile QR code</h2></div><span class="live-indicator"><i></i> Private token</span></div>
                <p class="profile-qr-copy">Use this code only with a trusted campus scanner. It contains your account’s access token, not your password.</p>
                @if ($profile->qr_token)
                    <div class="profile-qr-preview" data-qr-code data-qr-value="{{ $profile->qr_token }}" aria-live="polite">
                        <span class="qr-loading">Generating your QR image…</span>
                        <img alt="Your campus profile QR code" hidden>
                        <p class="qr-error" hidden>QR image generation failed. Reload this page and try again.</p>
                    </div>
                    <button class="secondary-button" type="button" data-qr-download disabled>Download QR image</button>
                @else
                    <x-empty-state icon="scan" eyebrow="QR NOT AVAILABLE" title="No profile code yet" description="A campus access token has not been assigned to this account." />
                @endif
            </section>
        </div>
    </main>
</x-layouts.app>
