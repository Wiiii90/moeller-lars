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
            $visibleSelectableCount = count($components);
            $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
            $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        @endphp

        <section class="admin-visual-stage admin-visual-stage--split admin-visual-stage--editor-preview"
            aria-label="Custom Page editor and live public preview">
            <div class="admin-visual-stage__pane admin-visual-stage__editor-pane">
                <h2 class="admin-section__kicker admin-visual-stage__heading">{{ __('Editor') }}</h2>
                <div class="admin-stage-tabs admin-stage-tabs--three" role="tablist" aria-label="Custom Page editing mode">
                    @foreach (['create' => 'Create', 'layout' => 'Layout', 'settings' => 'Settings'] as $panel => $label)
                        <button class="admin-action {{ $customStagePanel === $panel ? 'is-primary' : '' }}"
                            type="button" role="tab" aria-selected="{{ $customStagePanel === $panel ? 'true' : 'false' }}"
                            wire:click="showCustomStagePanel('{{ $panel }}')">{{ __($label) }}</button>
                    @endforeach
                </div>
                @if ($customStagePanel === 'create')
                    <form class="admin-stage-workspace admin-visual-stage__field-scroll custom-page-stage__create"
                        wire:submit.prevent="createStageComponent">
                        <label class="admin-stage-workspace__field">
                            <span>{{ __('Component') }}</span>
                            <select wire:model.live="stageQuickType" aria-label="Component type">
                                @foreach (array_keys($componentTypeOptions) as $type)
                                    <option value="{{ $type }}">{{ $componentTypeOptions[$type] }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if ($stageQuickType === 'text' || $stageQuickType === 'contact')
                            <label class="admin-stage-workspace__field">
                                <span>{{ __('Title') }}</span>
                                <input type="text" maxlength="160" wire:model="stageQuickTitle" placeholder="{{ __('Optional title') }}">
                            </label>
                        @endif
                        @if ($stageQuickType === 'text')
                            <label class="admin-stage-workspace__field">
                                <span>{{ __('Text') }}</span>
                                <textarea wire:model="stageQuickBody" rows="5" aria-label="Quick Rich Text content"></textarea>
                            </label>
                        @endif
                        @if (in_array($stageQuickType, \App\Models\CustomPageSetting::EMBEDDED_COMPONENT_TYPES, true))
                            <label class="admin-stage-workspace__field">
                                <span>{{ in_array($stageQuickType, ['gallery', 'hero_artworks'], true) ? __('Source gallery') : __('Source entry') }}</span>
                                <select wire:model="stageQuickSourceId" aria-label="Component source">
                                    <option value="">{{ __('Select source') }}</option>
                                    @foreach ($this->stageQuickSources() as $sourceId => $sourceName)
                                        <option value="{{ $sourceId }}">{{ $sourceName }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="admin-stage-workspace__field">
                            <span>{{ __('Place in') }}</span>
                            <select wire:model="stageQuickAreaId" aria-label="Component layout destination">
                                <option value="">{{ __('Full width') }}</option>
                                @foreach ($this->stageLayoutAreaOptions() as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="admin-action is-primary" type="submit">
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Add->mini()" />
                            {{ __('Create unpublished component') }}
                        </button>
                    </form>
                @elseif ($customStagePanel === 'layout')
                    @include('filament.pages.partials.custom-page-layout-stage')
                @else
                    @php($selectedLayout = $this->selectedStageLayout())
                    @php($selectedComponent = $this->selectedStageComponent())
                    <div class="admin-stage-workspace admin-visual-stage__field-scroll" aria-label="Selected component and layout settings">
                        @if ($selectedComponent !== null)
                            @php($component = $selectedComponent['block'])
                            @if ($selectedComponent['type'] === 'gallery')
                                <div class="admin-stage-workspace__group">
                                    <strong>{{ __('Gallery presentation') }}</strong>
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Arrangement') }}</span>
                                        <select wire:change="setStageEmbeddedProperty({{ $selectedComponent['index'] }}, 'gallery', 'gallery_presentation', $event.target.value)">
                                            <option value="sequence" @selected(($component['gallery_presentation'] ?? 'sequence') === 'sequence')>{{ __('Sequence') }}</option>
                                            <option value="two_columns" @selected(($component['gallery_presentation'] ?? 'sequence') === 'two_columns')>{{ __('Two columns') }}</option>
                                        </select>
                                    </label>
                                </div>
                            @elseif ($selectedComponent['type'] === 'hero_artworks')
                                <div class="admin-stage-workspace__group">
                                    <strong>{{ __('Hero Artworks') }}</strong>
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Artwork order') }}</span>
                                        <select wire:change="setStageEmbeddedProperty({{ $selectedComponent['index'] }}, 'hero_artworks', 'source_rule', $event.target.value)">
                                            @foreach (\App\Domain\Content\GalleryHeroConfigurationService::SOURCE_RULES as $rule => $label)
                                                <option value="{{ $rule }}" @selected(($component['source_rule'] ?? 'newest_artwork') === $rule)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            @endif
                            <div class="admin-stage-workspace__group">
                                <strong>{{ __('Boundaries') }}</strong>
                                <label class="admin-stage-workspace__field">
                                    <span>{{ __('Before component') }}</span>
                                    <select wire:change="setStageComponentBoundary({{ $selectedComponent['index'] }}, '{{ $selectedComponent['type'] }}', $event.target.value)">
                                        <option value="none" @selected(($component['layout_divider_before'] ?? 'none') === 'none')>{{ __('None') }}</option>
                                        @foreach ($dividerVariantOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(($component['layout_divider_before'] ?? 'none') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif
                        @if ($selectedLayout !== null)
                            @php($layout = $selectedLayout['block'])
                            @php($layoutId = $layout['id'])
                            <div class="admin-stage-workspace__group">
                                <strong>{{ __('Layout') }}</strong>
                                <label class="admin-stage-workspace__field">
                                    <span>{{ __('Arrangement') }}</span>
                                    <select wire:change="setStageLayoutProperty('{{ $layoutId }}', 'layout_mode', $event.target.value)">
                                        <option value="rows" @selected(($layout['layout_mode'] ?? 'rows') === 'rows')>Aligned rows</option>
                                        <option value="columns" @selected(($layout['layout_mode'] ?? 'rows') === 'columns')>Independent columns</option>
                                    </select>
                                </label>
                                <label class="admin-stage-workspace__field">
                                    <span>{{ __('Columns') }}</span>
                                    <select wire:change="setStageLayoutProperty('{{ $layoutId }}', 'columns', $event.target.value)">
                                        @for ($n = 1; $n <= 4; $n++)
                                            <option value="{{ $n }}" @selected((int) $layout['columns'] === $n)>{{ $n }}</option>
                                        @endfor
                                    </select>
                                </label>
                                @if (($layout['layout_mode'] ?? 'rows') === 'rows')
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Rows') }}</span>
                                        <select wire:change="setStageLayoutProperty('{{ $layoutId }}', 'rows', $event.target.value)">
                                            @for ($n = 1; $n <= 12; $n++)
                                                <option value="{{ $n }}" @selected((int) $layout['rows'] === $n)>{{ $n }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                @endif
                                @if ((int) $layout['columns'] === 2)
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Column widths') }}</span>
                                        <select wire:change="setStageLayoutProperty('{{ $layoutId }}', 'column_layout', $event.target.value)">
                                            @foreach (['equal' => 'Equal', 'left-wide' => '65 / 35', 'right-wide' => '35 / 65'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($layout['column_layout'] ?? 'equal') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                <label class="admin-stage-workspace__field">
                                    <span>{{ __('Spacing') }}</span>
                                    <select wire:change="setStageLayoutProperty('{{ $layoutId }}', 'gap', $event.target.value)">
                                        @foreach (['none' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'] as $value => $label)
                                            <option value="{{ $value }}" @selected($layout['gap'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="admin-stage-workspace__group">
                                <strong>{{ __('Area boundaries') }}</strong>
                                @foreach ($layout['areas'] as $areaIndex => $area)
                                    <label class="admin-stage-workspace__field">
                                        <span>{{ __('Area') }} {{ $areaIndex + 1 }}</span>
                                        <select wire:change="setStageLayoutAreaBorder('{{ $layoutId }}', '{{ $area['id'] }}', $event.target.value)">
                                            <option value="none" @selected(($area['border'] ?? 'none') === 'none')>{{ __('None') }}</option>
                                            @foreach ($dividerVariantOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(($area['border'] ?? 'none') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($selectedComponent === null)
                            <button class="admin-action" type="button" wire:click="showCustomStagePanel('layout')">
                                {{ __('Select a component in the canvas') }}
                            </button>
                        @endif
                    </div>
                @endif
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
