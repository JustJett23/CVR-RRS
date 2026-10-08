@props(['name', 'class' => null])

<svg {{ $attributes->class(['icon', $class]) }} data-icon="{{ $name }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('menu')
            <path d="M5 7h14M7 12h12M9 17h10" />
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
            @break
        @case('building')
            <path d="M4 21V5l8-3v19M4 21h16M12 21V9l8-3v15M7 8h2m-2 4h2m-2 4h2m8-4h1m-1 4h1" />
            @break
        @case('door-closed')
            <path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17M3 21h18M15 12h.01" />
            @break
        @case('door-open')
            <path d="M4 21V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v16M2 21h20M15 5l5-2v17l-5 1M12 12h.01" />
            @break
        @case('calendar-check')
            <path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
            <path d="m9 16 2 2 4-4" />
            @break
        @case('hourglass')
            <path d="M5 3h14M5 21h14M7 3v4l5 5 5-5V3m-10 18v-4l5-5 5 5v4" />
            @break
        @case('wrench')
            <path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4L14 13l-3-3 3.7-3.7Z" />
            @break
        @case('list-checks')
            <path d="M9 6h11M9 12h11M9 18h11M3.5 6l1.5 1.5L7.5 5M3.5 12l1.5 1.5L7.5 11M3.5 18l1.5 1.5L7.5 17" />
            @break
        @case('dashboard')
            <rect x="3" y="3" width="8" height="8" rx="1.5" />
            <rect x="13" y="3" width="8" height="5" rx="1.5" />
            <rect x="13" y="10" width="8" height="11" rx="1.5" />
            <rect x="3" y="13" width="8" height="8" rx="1.5" />
            @break
        @case('layers')
            <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5" />
            @break
        @case('calendar')
            <path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('package')
            <path d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm-9 5 9 5 9-5m-9 5v8m-4-15 9 5" />
            @break
        @case('chart')
            <path d="M4 19V5m0 14h17M8 15v-4m5 4V7m5 8V9" />
            @break
        @case('activity')
            <path d="M3 12h4l3-8 4 16 3-8h4" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" />
            <path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.8 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.8-1l-1.7.6-1.4-2.4 1.4-1.1a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.8-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.8 1l1.7-.6 1.4 2.4-1.4 1.1a8 8 0 0 1 0 2Z" transform="translate(-1 -1)" />
            @break
        @case('help')
            <circle cx="12" cy="12" r="9" />
            <path d="M9.6 9a2.5 2.5 0 1 1 4.3 1.8c-1.2 1.1-1.9 1.5-1.9 3.2m0 3h.01" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @case('center')
            <circle cx="12" cy="12" r="8" />
            <circle cx="12" cy="12" r="2" />
            <path d="M12 2v2m0 16v2M2 12h2m16 0h2" />
            @break
        @case('rotate')
            <path d="M20 7v5h-5M4 17v-5h5" />
            <path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3" />
            @break
        @case('maximize')
            <path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3" />
            @break
    @endswitch
</svg>
