@props(['eyebrow', 'title', 'description', 'actionLabel' => null, 'actionHref' => null])

<div class="page-heading">
    <div>
        <p class="eyebrow">{{ $eyebrow }} <span class="eyebrow-line"></span></p>
        <h1>{{ $title }}</h1>
        <p class="page-subtitle">{{ $description }}</p>
    </div>
    @if ($actionLabel && $actionHref)
        <div class="heading-meta"><a class="primary-button" href="{{ $actionHref }}">{{ $actionLabel }}</a></div>
    @endif
</div>
