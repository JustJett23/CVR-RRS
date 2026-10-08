@props(['action', 'label', 'icon'])

<button {{ $attributes->merge(['class' => 'map-control', 'type' => 'button', 'data-map-action' => $action, 'aria-label' => $label, 'title' => $label, 'data-tooltip' => $label]) }}><x-icon :name="$icon" /></button>
