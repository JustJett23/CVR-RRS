<x-layouts.app title="CVR" page-title="Equipment" current-page="equipment">
    <main class="dashboard page-content">
        <x-page-title eyebrow="OPERATIONS" title="Equipment" description="Track shared campus resources, condition, storage location, and quantities." />

        <section class="resource-grid" aria-label="Equipment categories">
            <article class="resource-tile"><span class="resource-icon"><x-icon name="package" /></span><div><h2>Audio &amp; visual</h2><p>Projectors, microphones, speakers, and displays</p></div><span class="resource-count">—</span></article>
            <article class="resource-tile"><span class="resource-icon"><x-icon name="building" /></span><div><h2>Computing</h2><p>Computers and related classroom equipment</p></div><span class="resource-count">—</span></article>
            <article class="resource-tile"><span class="resource-icon"><x-icon name="layers" /></span><div><h2>Furniture</h2><p>Chairs, tables, and other shared resources</p></div><span class="resource-count">—</span></article>
        </section>

        <section class="content-panel" aria-labelledby="equipment-inventory-title">
            <div class="content-panel-heading"><div><p class="eyebrow">RESOURCE INVENTORY</p><h2 id="equipment-inventory-title">Campus equipment</h2></div><span class="sample-tag">No inventory connected</span></div>
            <x-empty-state icon="package" eyebrow="INVENTORY NEEDED" title="Equipment records are not available" description="Quantities, condition, availability, and storage locations need to come from an approved campus inventory." action-label="View equipment request flow" :action-href="route('equipment-requests')" />
        </section>
    </main>
</x-layouts.app>
