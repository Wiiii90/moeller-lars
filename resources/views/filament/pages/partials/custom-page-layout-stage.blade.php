@php
    $stageItems = $stageItems ?? $this->stageLayoutItems();
    $stageAreaId = $stageAreaId ?? 'root';
    $stageDepth = $stageDepth ?? 0;
@endphp
@if ($stageDepth === 0)
    <div class="custom-page-layout-stage admin-visual-stage__field-scroll" aria-label="Page layout canvas">
@endif
<div class="custom-page-layout-stage__flow{{ $stageItems === [] ? ' custom-page-layout-stage__flow--empty' : '' }}"
    wire:key="custom-layout-group-{{ $stageAreaId }}"
    wire:sort="sortStageComponent"
    wire:sort:group="custom-layout-canvas"
    wire:sort:group-id="{{ $stageAreaId }}"
    @if ($stageItems === []) wire:sort:config="{ emptyInsertThreshold: 0 }" @endif>
    @foreach ($stageItems as $component)
        @php
            $type = (string) ($component['type'] ?? '');
            $index = (int) ($component['_root_index'] ?? -1);
            $target = $index.':'.$type;
            $isLayout = $type === 'layout';
            $selected = $stageLayoutFocusedTarget === $target;
            $name = $type === 'text' && filled($component['title'] ?? null)
                ? (string) $component['title']
                : (\App\Filament\Support\ComponentEditorTypes::LABELS[$type] ?? 'Component');
            $preview = $type === 'text' ? trim(strip_tags((string) ($component['body'] ?? ''))) : '';
        @endphp
        <div class="custom-page-layout-stage__item{{ $selected ? ' is-selected' : '' }}"
            wire:key="custom-layout-component-{{ $index }}-{{ $type }}"
            wire:sort:item="{{ $target }}">
            @if ($isLayout)
                <section class="custom-page-layout-stage__layout">
                    <div class="custom-page-layout-stage__header">
                        <button class="admin-drag-handle" type="button" wire:sort:handle
                            aria-label="Move layout" title="Move layout">
                            <x-filament::icon icon="heroicon-m-bars-3" />
                        </button>
                        <button class="custom-page-layout-stage__identity" type="button"
                            wire:click="focusStageComponent({{ $index }}, 'layout')"
                            aria-pressed="{{ $selected ? 'true' : 'false' }}">
                            <x-filament::icon icon="heroicon-m-squares-2x2" />
                            {{ __('Layout') }}
                        </button>
                        <span class="custom-page-layout-stage__dimensions">
                            {{ (int) ($component['columns'] ?? 1) }} × {{ (int) ($component['rows'] ?? 1) }}
                        </span>
                    </div>
                    <div class="custom-page-layout-stage__cells custom-page-layout-stage__cells--columns-{{ $component['columns'] }} custom-page-layout-stage__cells--layout-{{ $component['column_layout'] ?? 'equal' }}">
                        @foreach ($component['areas'] as $areaIndex => $area)
                            <div class="custom-page-layout-stage__cell">
                                <span class="custom-page-layout-stage__area-label">
                                    {{ ($component['layout_mode'] ?? 'rows') === 'columns' ? __('Column') : __('Area') }} {{ $areaIndex + 1 }}
                                </span>
                                @include('filament.pages.partials.custom-page-layout-stage', [
                                    'stageItems' => $area['blocks'] ?? [],
                                    'stageAreaId' => $area['id'],
                                    'stageDepth' => $stageDepth + 1,
                                ])
                            </div>
                        @endforeach
                    </div>
                </section>
            @else
                <div class="custom-page-layout-stage__component">
                    <button class="admin-drag-handle" type="button" wire:sort:handle
                        aria-label="Move {{ $name }}" title="Move component">
                        <x-filament::icon icon="heroicon-m-bars-3" />
                    </button>
                    <button class="custom-page-layout-stage__identity" type="button"
                        wire:click="focusStageComponent({{ $index }}, '{{ $type }}')"
                        aria-pressed="{{ $selected ? 'true' : 'false' }}">
                        <span>{{ $name }}</span>
                        @if ($preview !== '')
                            <small>{{ \Illuminate\Support\Str::limit($preview, 72) }}</small>
                        @endif
                    </button>
                    @unless ($component['published'] ?? true)
                        <x-filament::icon icon="heroicon-m-eye-slash" class="custom-page-layout-stage__unpublished" />
                    @endunless
                </div>
            @endif
        </div>
    @endforeach
</div>
@if ($stageDepth === 0)
    </div>
@endif
