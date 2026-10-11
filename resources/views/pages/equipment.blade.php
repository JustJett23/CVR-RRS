<x-layouts.app title="CVR" page-title="Equipment" current-page="equipment">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Equipment" description="Track shared campus resources, condition, storage location, and quantities." />

        <section class="resource-grid" aria-label="Equipment categories">
            @foreach ($equipment->groupBy('category') as $category => $items)
                <details class="resource-tile resource-dropdown">
                    <summary><span class="resource-icon"><x-icon name="package" /></span><span><h2>{{ $category ?: 'Other equipment' }}</h2><p>{{ $items->count() }} database item{{ $items->count() === 1 ? '' : 's' }}</p></span><span class="resource-count">⌄</span></summary>
                    <div class="resource-dropdown-list">@foreach ($items as $item)<div><strong>{{ $item->name }}</strong><span>{{ $item->available_quantity }} of {{ $item->quantity }} available</span></div>@endforeach</div>
                </details>
            @endforeach
        </section>

        <section class="content-panel" aria-labelledby="equipment-inventory-title">
            <div class="content-panel-heading"><div><p class="eyebrow">RESOURCE INVENTORY</p><h2 id="equipment-inventory-title">Campus equipment</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            @if ($equipment->isEmpty())
                <x-empty-state icon="package" eyebrow="NO INVENTORY" title="No equipment records" description="Equipment will appear here when it is entered in the campus database." action-label="Open equipment requests" :action-href="route('equipment-requests')" />
            @else
                <p class="availability-note">{{ $equipment->count() }} equipment records loaded from the campus database.</p>
            @endif
        </section>
    </main>
</x-layouts.app>
