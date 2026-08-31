@php
    $summary = app(\App\Domain\Publication\PublicationService::class)->pendingSummary();
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        {{ $summary['total'] }} {{ \Illuminate\Support\Str::plural('pending change', $summary['total']) }}
    </p>

    @foreach ($summary['groups'] as $group)
        <div class="border-t border-gray-200 pt-3 first:border-t-0 first:pt-0 dark:border-white/10">
            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $group['area'] }}</p>
            <div class="mt-1 flex items-baseline justify-between gap-4 text-sm text-gray-600 dark:text-gray-300">
                <span>{{ $group['entity'] }}</span>
                <span>{{ $group['count'] }}</span>
            </div>
        </div>
    @endforeach
</div>
