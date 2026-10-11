<x-layouts.app title="CVR" page-title="Activity Logs" current-page="activity-logs">
    <main id="main-content" class="dashboard page-content">
        <x-page-title eyebrow="INSIGHTS" title="Activity Logs" description="Recent reservation, equipment, and account changes recorded by the server." />
        <section class="content-panel" aria-labelledby="activity-feed-title">
            <div class="content-panel-heading"><div><p class="eyebrow">SYSTEM HISTORY</p><h2 id="activity-feed-title">Recent activity</h2></div><span class="live-indicator"><i></i> Live database</span></div>
            @if($activities->isEmpty())
                <x-empty-state icon="activity" eyebrow="NO AUDIT EVENTS" title="No recorded activity yet" description="Reservation, equipment, and account changes will be listed here." />
            @else
                <div class="table-scroll"><table class="data-table"><thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th></tr></thead><tbody>
                    @foreach($activities as $activity)
                        <tr><td><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, Y g:i A') }}</time></td><td>{{ $activity->user?->name ?? 'System' }}</td><td>{{ str($activity->action)->replace('.', ' · ')->headline() }}</td><td>{{ class_basename($activity->subject_type ?? '') }}{{ $activity->subject_id ? ' #'.$activity->subject_id : '' }}</td></tr>
                    @endforeach
                </tbody></table></div>
                {{ $activities->links() }}
            @endif
        </section>
    </main>
</x-layouts.app>
