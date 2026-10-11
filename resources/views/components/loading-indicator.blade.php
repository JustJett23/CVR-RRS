@props(['message' => 'Working on it…'])

<div class="cve-loader" data-cve-loader role="status" aria-live="polite" aria-busy="true" hidden>
    <div class="cve-loader-mark" aria-hidden="true">
        <svg viewBox="0 0 256 256" fill="none">
            <g class="loader-orbit"><circle cx="128" cy="27" r="6" /></g>
            <path class="loader-cell loader-cell-center" d="M154 128 141 150.5h-26L102 128l13-22.5h26L154 128Z" />
            <path class="loader-cell" d="M154 76 141 98.5h-26L102 76l13-22.5h26L154 76Z" />
            <path class="loader-cell" d="m199 102-13 22.5h-26L147 102l13-22.5h26l13 22.5Z" />
            <path class="loader-cell" d="m199 154-13 22.5h-26L147 154l13-22.5h26l13 22.5Z" />
            <path class="loader-cell" d="m154 180-13 22.5h-26L102 180l13-22.5h26l13 22.5Z" />
            <path class="loader-cell" d="m109 154-13 22.5H70L57 154l13-22.5h26l13 22.5Z" />
            <path class="loader-cell" d="m109 102-13 22.5H70L57 102l13-22.5h26l13 22.5Z" />
            <circle cx="128" cy="128" r="7" class="loader-node" />
        </svg>
    </div>
    <strong data-cve-loader-message>{{ $message }}</strong>
    <small class="cve-loader-quote">“Make space for what matters.”</small>
</div>
