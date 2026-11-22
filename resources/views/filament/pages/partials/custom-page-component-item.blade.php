@php
    $target = is_array($componentBlock)
        ? (string) ($componentBlock['_root_index'] ?? -1).':'.(string) ($componentBlock['type'] ?? '')
        : ($pageComponent['target'] ?? '');
    $row = is_array($componentBlock)
        ? ($componentRows[$target] ?? null)
        : $pageComponent;
@endphp
@if (is_array($row))
    <article class="custom-page-component admin-hierarchy__group"
        wire:key="custom-component-{{ $target }}"
        @if ($reorderEnabled) wire:sort:item="{{ $target }}" @endif>
        @include('filament.pages.partials.custom-page-component-row', [
            'row' => $row,
            'reorderEnabled' => $reorderEnabled,
            'componentTypeOptions' => $componentTypeOptions,
        ])

        @if ($row['embedded_children'] !== [])
            <div class="custom-page-component__embedded-children" role="group"
                aria-label="{{ $row['type_label'] }} page template">
                @foreach ($row['embedded_children'] as $child)
                    <div class="custom-page-component__embedded-child"
                        wire:key="embedded-page-{{ $row['index'] }}-{{ $child['id'] }}">
                        <span class="custom-page-component__embedded-child-title">
                            {{ __('Source page') }}: {{ $child['title'] }}
                        </span>
                        @if ($child['workspace_url'])
                            <a class="admin-action" href="{{ $child['workspace_url'] }}">
                                {{ __('Open source editor') }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($reorderEnabled && ($componentBlock['type'] ?? null) === 'layout')
            <div class="admin-hierarchy__children custom-page-component__layout-members"
                aria-label="Components within this layout">
                @foreach ($componentBlock['areas'] ?? [] as $areaIndex => $area)
                    <div class="custom-page-component__layout-area" wire:key="custom-layout-area-{{ $area['id'] }}">
                        <span class="custom-page-component__layout-area-label">
                            {{ ($componentBlock['layout_mode'] ?? 'rows') === 'columns' ? __('Column') : __('Area') }} {{ $areaIndex + 1 }}
                        </span>
                        <div class="admin-hierarchy__children-rows custom-page-component__layout-area-rows"
                            role="rowgroup"
                            aria-label="Components in area {{ $areaIndex + 1 }}"
                            data-drop-target="true"
                            wire:sort="sortComponent"
                            wire:sort:group="custom-components"
                            wire:sort:group-id="{{ $area['id'] }}"
                            @if (($area['blocks'] ?? []) === []) wire:sort:config="{ emptyInsertThreshold: 0 }" @endif>
                            @foreach ($area['blocks'] ?? [] as $componentBlockChild)
                                @include('filament.pages.partials.custom-page-component-item', [
                                    'componentBlock' => $componentBlockChild,
                                    'pageComponent' => null,
                                    'componentRows' => $componentRows,
                                    'reorderEnabled' => true,
                                ])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
@endif
