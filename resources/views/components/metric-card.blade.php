@props(['label', 'value', 'caption', 'context', 'icon', 'tone' => 'blue'])

<article {{ $attributes->class(['metric-card', 'metric-card-'.$tone]) }}>
    <div class="metric-top">
        <span class="metric-label">{{ $label }}</span>
        <span class="metric-symbol" aria-hidden="true"><x-icon :name="$icon" /></span>
    </div>
    <div class="metric-value">{{ $value }}</div>
    <div class="metric-foot">
        @if ($tone === 'green')
            <span class="status-inline status-available"><i></i>{{ $caption }}</span>
        @else
            <span class="metric-caption">{{ $caption }}</span>
        @endif
        <span class="metric-context">{{ $context }}</span>
    </div>
</article>
