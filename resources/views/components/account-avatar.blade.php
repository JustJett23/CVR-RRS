@props(['name' => ''])

@php
    $avatarName = trim($name);
    $avatarUrl = auth()->user()?->profile_photo_path ? route('profile.photo') : null;
@endphp

<span {{ $attributes->merge(['class' => 'profile-avatar'.($avatarUrl ? ' has-photo' : '')]) }} aria-hidden="true">
    @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" loading="lazy">
    @else
        {{ collect(preg_split('/\s+/', $avatarName))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('') }}
    @endif
</span>
