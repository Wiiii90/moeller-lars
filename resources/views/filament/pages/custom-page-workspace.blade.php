<x-filament-panels::page>
    <x-admin.workspace :title="$pageTitle" class="custom-page-workspace">
        <x-admin.metrics :columns="6" class="custom-page-status-metrics admin-metrics--six-state" aria-label="Custom page overview">
            @foreach ($metrics as $metric)
                @php
                    $metricRole = match ($metric['label']) {
                        'Components' => 'components',
                        'Rich Text' => 'entries',
                        'Media' => 'images',
                        'Visits' => 'visits',
                        'Views' => 'views',
                        'Contact messages' => 'contact',
                        default => 'secondary',
                    };
                @endphp
                <x-admin.metric class="custom-page-metric custom-page-metric--{{ $metricRole }}" :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        @php
            $reorderEnabled = trim($componentSearch) === ''
                && $componentType === 'any'
                && $page === 1
                && $total <= $pageSize;
            $selectedItemCount = count($selectedComponentTargets);
            $selectedParents = collect($components)->filter(static fn (array $component): bool =>
                in_array($component['target'] ?? null, $selectedComponentTargets, true)
            )->values();
            $canMoveSelected = $selectedItemCount > 0 && $reorderEnabled;
            $canPublishSelected = $selectedParents->contains(static fn (array $component): bool => ($component['published'] ?? false) === false);
            $canUnpublishSelected = $selectedParents->contains(static fn (array $component): bool => ($component['published'] ?? false) === true);
            $canDeleteSelected = $this->canDeleteSelectedComponents();
            $canActivateSelected = $selectedItemCount === 1
                && $selectedParents->count() === 1
                && $selectedParents->first()['editable'];
            $visibleSelectableCount = count($components);
            $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
            $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        @endphp

        <section class="admin-visual-stage admin-visual-stage--split admin-visual-stage--editor-preview"
            aria-label="Custom Page editor and live public preview">
            <div class="admin-visual-stage__pane admin-visual-stage__editor-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Editor') }}</h2>
                <x-admin.stage-tool-row label="Editor actions">
                    <div class="admin-stage-tool-group">
                        <button class="admin-action admin-action--with-icon" type="button"
                            wire:click="mountAction('addComponent', { initialType: 'grid' })">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" class="admin-action__icon" />
                            <span class="admin-action__label">{{ __('Add Grid') }}</span>
                        </button>
                        <button class="admin-action admin-action--with-icon" type="button"
                            wire:click="activateSelectedComponent" @disabled(! $canActivateSelected)>
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                            <span class="admin-action__label">{{ __('Activate') }}</span>
                        </button>
                    </div>
                </x-admin.stage-tool-row>
                @include('filament.pages.partials.custom-page-layout-stage')
            </div>
            <div class="admin-visual-stage__pane admin-visual-stage__preview-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Live preview') }}</h2>
                <x-admin.live-preview
                    :heading="false"
                    :url="$this->customStagePreviewUrl()"
                    frame-title="Interactive Custom Page preview"
                    wire:key="custom-page-live-preview"
                >
                    <x-slot:focusAction>
                        <button class="admin-action admin-stage-tool-icon {{ $customPreviewAutoFocus ? 'is-primary' : '' }}"
                            type="button" wire:click="toggleCustomPreviewAutoFocus"
                            aria-label="Auto-focus preview to selection"
                            aria-pressed="{{ $customPreviewAutoFocus ? 'true' : 'false' }}"
                            title="{{ $customPreviewAutoFocus ? 'Auto-focus selection on' : 'Auto-focus selection off' }}">
                            <x-filament::icon icon="heroicon-m-cursor-arrow-rays" />
                        </button>
                    </x-slot:focusAction>
                </x-admin.live-preview>
            </div>
        </section>

        <div class="admin-visual-stage-followup">
        @include('filament.pages.partials.custom-page-workspace-controls')

        @include('filament.pages.partials.custom-page-workspace-sequence')

        @include('filament.pages.partials.custom-page-workspace-footer')
        </div>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-panels::page>
