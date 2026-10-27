@php($isStaff = in_array(auth()->user()?->role, ['admin', 'creator'], true))
<x-layouts.app title="CVR" page-title="Equipment" current-page="equipment">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Campus Equipment" description="Check stock before borrowing shared presentation and event equipment." />

        <section class="equipment-catalog-grid" aria-label="Campus equipment inventory">
            @forelse ($equipment as $item)
                @php($equipmentIcon = match ($item->category) { 'Presentation' => 'projector', 'Cables' => 'cable', 'Power' => 'plug', default => 'package' })
                <article class="equipment-card">
                    <div class="equipment-card-art" aria-hidden="true"><x-icon :name="$equipmentIcon" /></div>
                    <div class="equipment-card-body">
                        <p class="eyebrow">{{ $item->category ?: 'CAMPUS EQUIPMENT' }}</p>
                        <h2>{{ $item->name }}</h2>
                        @if ($item->description)<p class="equipment-card-description">{{ $item->description }}</p>@endif
                        <dl class="equipment-card-facts">
                            <div><dt>Availability</dt><dd class="{{ $item->available_quantity > 0 ? 'is-available' : 'is-unavailable' }}">{{ $item->available_quantity > 0 ? 'Available' : 'Out of stock' }}</dd></div>
                            <div><dt>How many</dt><dd>{{ $item->available_quantity }} available · {{ $item->quantity }} total</dd></div>
                        </dl>
                    </div>
                    <footer class="equipment-card-footer">
                        @if (! $isStaff)
                            @if ($item->available_quantity > 0)
                                <a class="primary-button equipment-borrow-button" href="{{ route('equipment-requests', ['equipment_id' => $item->id]) }}">Borrow {{ $item->name }}</a>
                            @else
                                <span class="secondary-button equipment-borrow-button is-disabled" aria-disabled="true">Borrow {{ $item->name }}</span>
                            @endif
                        @else
                            <span class="equipment-stock-note">Inventory managed by available stock</span>
                        @endif
                    </footer>
                </article>
            @empty
                <div class="content-panel equipment-catalog-empty"><x-empty-state icon="package" eyebrow="NO INVENTORY" title="No equipment records" description="Sample equipment appears here after the equipment inventory seeder runs." /></div>
            @endforelse
        </section>

        <p class="map-data-note">Availability and quantities come from the campus equipment database.</p>
    </main>
</x-layouts.app>
