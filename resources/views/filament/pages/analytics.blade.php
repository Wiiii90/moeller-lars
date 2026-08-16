<x-filament-panels::page>
    <section>
        <h2 class="text-lg font-semibold">Human analytics · last 30 days</h2>

        @if (($matomo['status'] ?? null) === 'available')
            <dl class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($matomo['metrics'] as $name => $value)
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-gray-500">{{ str_replace('_', ' ', $name) }}</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="mt-3">{{ $matomo['message'] ?? 'Matomo analytics are unavailable.' }}</p>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="text-lg font-semibold">Application operational aggregates · last 30 days</h2>

        @if ($operational === [])
            <p class="mt-3">No operational aggregate data is available yet.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th>Date</th><th>Metric</th><th>Value</th><th>Unit</th><th>Samples</th></tr></thead>
                    <tbody>
                    @foreach ($operational as $metric)
                        <tr>
                            <td>{{ $metric['date'] }}</td>
                            <td>{{ $metric['name'] }}</td>
                            <td>{{ $metric['value'] }}</td>
                            <td>{{ $metric['unit'] }}</td>
                            <td>{{ $metric['sample_count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-panels::page>
