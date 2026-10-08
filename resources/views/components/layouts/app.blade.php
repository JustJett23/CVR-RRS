<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#153548">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/campus-venue-engine-logo.svg') }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell" id="app-shell">
        {{ $slot }}
    </div>
</body>
</html>
