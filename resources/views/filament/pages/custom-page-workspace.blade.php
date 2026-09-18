<x-filament-panels::page>
    <x-admin.workspace :title="$pageTitle" class="custom-page-workspace">
        <x-admin.metrics :columns="6" aria-label="Custom page overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        @php
            $reorderEnabled = trim($componentSearch) === ''
                && $componentType === 'any'
                && $page === 1
                && $total <= $pageSize;
            $selectedParentCount = count($selectedComponentTargets);
            $selectedChildCount = count($selectedChildTargets);
            $selectedItemCount = $selectedParentCount + $selectedChildCount;
            $selectedParents = collect($components)
                ->filter(static fn (array $component): bool => in_array($component['target'] ?? null, $selectedComponentTargets, true))
                ->values();
            $selectedChildren = collect($components)
                ->flatMap(static fn (array $component): array => is_array($component['children'] ?? null) ? $component['children'] : [])
                ->filter(static fn (array $child): bool => in_array($child['target'] ?? null, $selectedChildTargets, true))
                ->values();
            $canMoveSelected = $selectedItemCount > 0 && $reorderEnabled;
            $canPublishSelected = $selectedItemCount > 0
                && ($selectedParents->contains(static fn (array $component): bool => ($component['published'] ?? false) === false)
                    || $selectedChildren->contains(static fn (array $child): bool => ($child['published'] ?? false) === false));
            $canUnpublishSelected = $selectedItemCount > 0
                && ($selectedParents->contains(static fn (array $component): bool => ($component['published'] ?? false) === true)
                    || $selectedChildren->contains(static fn (array $child): bool => ($child['published'] ?? false) === true));
            $canDeleteSelected = $selectedItemCount > 0;
            $visibleSelectableCount = collect($components)->sum(
                static fn (array $component): int => 1 + count(is_array($component['children'] ?? null) ? $component['children'] : []),
            );
            $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
            $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        @endphp

        @include('filament.pages.partials.custom-page-workspace-controls')

        @include('filament.pages.partials.custom-page-workspace-sequence')

        @include('filament.pages.partials.custom-page-workspace-footer')
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
