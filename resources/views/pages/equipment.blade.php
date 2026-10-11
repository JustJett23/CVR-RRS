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

        @if ($canManageEquipment)
            <section class="content-panel" aria-labelledby="inventory-management-title">
                <div class="content-panel-heading"><div><p class="eyebrow">ADMINISTRATION</p><h2 id="inventory-management-title">Manage inventory</h2></div><span class="live-indicator"><i></i> Database writes</span></div>
                <form class="stack-form" data-inventory-form data-endpoint="{{ route('api.equipment.store') }}">
                    @csrf
                    <div class="form-grid-two">
                        <label class="filter-field"><span>Equipment name</span><input name="name" maxlength="255" required></label>
                        <label class="filter-field"><span>Category</span><input name="category" maxlength="48"></label>
                        <label class="filter-field"><span>Total quantity</span><input name="quantity" type="number" min="0" max="1000000" required></label>
                        <label class="filter-field"><span>Status</span><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                    </div>
                    <label class="filter-field"><span>Description (optional)</span><textarea name="description" rows="2" maxlength="2000"></textarea></label>
                    <p class="form-message" data-inventory-form-message role="status" aria-live="polite"></p>
                    <div class="reservation-actions"><button class="primary-button" type="submit">Add equipment</button></div>
                </form>
                <p class="directory-message" data-inventory-list-message role="status" aria-live="polite">Loading inventory…</p>
                <div class="request-list inventory-admin-list" data-inventory-list data-endpoint="{{ route('api.equipment.index') }}" data-item-endpoint="{{ url('/api/equipment') }}" data-store-endpoint="{{ route('api.equipment.store') }}"></div>
            </section>
        @endif
    </main>
</x-layouts.app>
