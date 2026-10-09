@props(['icon' => 'building', 'eyebrow' => 'DATA SOURCE NEEDED', 'title', 'description', 'actionLabel' => null, 'actionHref' => null])

<div {{ $attributes->class(['empty-state']) }} role="status">
    <span class="empty-state-icon" aria-hidden="true"><x-icon :name="$icon" /></span>
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h3>{{ $title }}</h3>
    <p>{{ $description }}</p>
    @if ($actionLabel && $actionHref)
        <a class="secondary-button" href="{{ $actionHref }}">{{ $actionLabel }}</a>
    @endif
</div>
