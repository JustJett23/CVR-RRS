<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#213448">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/campus-venue-engine-logo.svg') }}">
    <title>CVR · Sign in</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-screen">
        <div class="auth-topbar">
            <a class="auth-back" href="{{ route('landing') }}">← Back to CVR</a>
            <div class="landing-brand"><img src="{{ asset('images/campus-venue-engine-logo.svg') }}" alt="" width="27" height="27"><span>Campus Venue Engine</span></div>
        </div>
        <section class="auth-layout" aria-labelledby="login-title">
            <div class="auth-welcome">
                <p class="landing-kicker"><span></span> NPC CAMPUS ACCESS</p>
                <h1 id="login-title">Welcome<br><em>back.</em></h1>
                <p>Sign in with your NPC student or recognized organization account to continue.</p>
                <div class="auth-trust"><span><x-icon name="shield" /></span><small>Access is limited to verified NPC students and organization members.</small></div>
            </div>
            <div class="auth-form-panel">
                <div class="auth-form-heading"><p class="eyebrow">SECURE SIGN IN</p><h2>Identify your organization</h2><p>Scan your organization ID, then enter your account details.</p></div>
                @if ($errors->any())
                    <div class="notice-panel" role="alert">{{ $errors->first() }}</div>
                @endif
                <form id="login-form" method="POST" action="{{ route('login.store') }}" class="stack-form" data-loading-form data-loading-message="Validating your NPC access…">
                    @csrf
                    <div class="org-scanner" data-scanner>
                        <div class="scanner-frame">
                            <video data-scanner-video playsinline muted aria-label="Organization ID camera preview"></video>
                            <div class="scanner-placeholder" data-scanner-placeholder><span class="scanner-corner"></span><x-icon name="scan" /><strong data-scanner-placeholder-title>Front of ID</strong><small>Place the correct side inside the frame</small></div>
                            <div class="scanner-line" aria-hidden="true"></div>
                        </div>
                        <div class="scanner-stage" data-scanner-stage>Step 1 of 2 · Front of ID</div>
                        <p class="scanner-stage-hint" data-scanner-stage-hint>Show the front side with your photo, name, course, and student number.</p>
                        <p class="scanner-status" data-scanner-status role="status">Use your camera to scan the organization ID.</p>
                        <p class="scanner-result-status" data-scanner-result-status role="status" hidden></p>
                        <div class="scanner-quality" data-scanner-quality aria-live="polite" hidden>
                            <span class="quality-item is-neutral" data-quality-light>Lighting: waiting</span>
                            <span class="quality-item is-neutral" data-quality-focus>Focus: waiting</span>
                            <span class="quality-item is-neutral" data-quality-frame>Framing: keep all edges visible</span>
                        </div>
                        <button class="secondary-button scanner-action" type="button" data-scanner-start><x-icon name="scan" /> Start front scanner</button>
                        <div class="scanner-secondary-actions">
                            <label class="secondary-button scanner-upload-action"><span data-scanner-upload-label>Capture and read front ID</span><input data-scanner-upload type="file" accept="image/*" capture="environment" multiple></label>
                            <button class="secondary-button scanner-next" type="button" data-scanner-next hidden>Continue to back ID <span aria-hidden="true">→</span></button>
                        </div>
                        <label class="filter-field scanner-manual"><span>Organization ID</span><input data-scanner-input type="text" name="organization_id" placeholder="Enter ID if scanning is unavailable" autocomplete="off"></label>
                        <div class="scanner-results" data-scanner-results hidden>
                            <p class="scanner-results-title">Review extracted details</p>
                            <p class="scanner-results-note" data-scanner-results-note>Confirm these values before continuing.</p>
                            <label class="filter-field"><span>Full name</span><input data-scanner-name type="text" name="scanned_name" autocomplete="name" readonly></label>
                            <label class="filter-field"><span>Course / program</span><input data-scanner-course type="text" name="scanned_course" readonly></label>
                            <label class="filter-field"><span>Student number</span><input data-scanner-student-number type="text" name="scanned_student_number" readonly></label>
                            <label class="filter-field"><span>Contact number</span><input data-scanner-contact type="tel" name="scanned_contact" autocomplete="tel" placeholder="09XXXXXXXXX" readonly></label>
                            <label class="filter-field"><span>Emergency contact</span><input data-scanner-emergency type="text" name="emergency_contact" autocomplete="off" placeholder="Name and/or Philippine contact number" readonly></label>
                            <div class="scanner-validation" data-scanner-validation aria-live="polite"></div>
                            <button class="primary-button scanner-validate-button" type="button" data-scanner-validate disabled>Validate extracted details <span aria-hidden="true">↗</span></button>
                            <label class="check-field scanner-remember"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
                            <p class="scanner-validation-message" data-scanner-validation-message role="alert" aria-live="assertive"></p>
                            <div class="scanner-email-requirement" data-scanner-email-requirement hidden><strong>Institutional email needed for incomplete scans</strong><span>Enter your NPC address when one or more required ID details were not read.</span><label class="filter-field"><span>Institutional email</span><input data-scanner-email type="email" autocomplete="email" placeholder="name@navotaspolytechniccollege.edu.ph"></label></div>
                        </div>
                    </div>
                    <button class="text-link auth-email-toggle" type="button" data-email-login-toggle>Sign in by Institutional Email</button>
                    <div class="auth-credentials-panel" data-email-login-panel hidden>
                        <div class="auth-divider"><span>Account credentials</span></div>
                        <label class="filter-field"><span>Institutional email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email"></label>
                        <label class="filter-field"><span>Password</span><input type="password" name="password" autocomplete="current-password"></label>
                        <div class="auth-form-options"><label class="check-field"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label><span class="text-link">Forgot password? Contact your campus administrator.</span></div>
                    </div>
                    <button class="primary-button" type="submit" data-email-submit hidden>Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-footnote">Not an NPC student or organization member? Access is restricted to verified campus users.</p>
            </div>
        </section>
    </main>
    <x-loading-indicator />
</body>
</html>
