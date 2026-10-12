<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#213448">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/campus-venue-engine-logo.svg') }}">
    <title>CVR · Campus Venue Engine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-body">
    <main class="landing-page">
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-brand"><img src="{{ asset('images/campus-venue-engine-logo.svg') }}" alt="" width="27" height="27"><span>Campus Venue Engine</span></div>
            <div class="landing-hero-copy">
                <p class="landing-kicker"><span></span> ROOM RESERVATION SYSTEM</p>
                <h1 id="landing-title">Make space for<br><em>what matters.</em></h1>
                <p class="landing-intro">One calm place to discover campus rooms, see what is available, and move every organization gathering forward.</p>
                <a class="landing-cta" href="{{ route('login') }}">Get started <span aria-hidden="true">↗</span></a>
                <p class="landing-note">For NPC students and recognized organization members.</p>
            </div>
            <div class="landing-orbit" aria-label="Campus spaces orbit">
                <div class="orbit-ring orbit-ring-outer"></div>
                <div class="orbit-ring orbit-ring-one"></div>
                <div class="orbit-ring orbit-ring-two"></div>
                <div class="orbit-track orbit-track-rooms"><span class="orbit-node">ROOMS</span></div>
                <div class="orbit-track orbit-track-people"><span class="orbit-node">PEOPLE</span></div>
                <div class="orbit-track orbit-track-time"><span class="orbit-node">TIME</span></div>
                <div class="orbit-core">
                    <picture>
                        <source type="image/webp" srcset="{{ asset('assets/npc/npc-seal-74.webp') }} 2x, {{ asset('assets/npc/npc-seal-111.webp') }} 3x">
                        <img src="{{ asset('assets/npc/npc-seal.png') }}" alt="" width="37" height="37" decoding="async">
                    </picture>
                    <strong>NPC</strong>
                    <small>VENUE MAP</small>
                </div>
            </div>
        </section>
        <section class="landing-strip" aria-label="Campus Venue Engine features">
            <div><span class="landing-strip-index">01</span><strong>Find a room</strong><small>Explore the NPC building floor by floor.</small></div>
            <div><span class="landing-strip-index">02</span><strong>Plan together</strong><small>Reserve with your organization details.</small></div>
            <div><span class="landing-strip-index">03</span><strong>Move with confidence</strong><small>See schedules and room status live.</small></div>
        </section>
    </main>
</body>
</html>
