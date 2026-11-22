{{-- The existing Stage owns placements. Layout areas are not subordinate Pages. --}}
<div class="custom-page-layout-stage" aria-label="Custom Page layout">
    @foreach ($this->layoutStageLayouts() as $layout)
        <section class="custom-page-layout-stage__grid" aria-label="Layout {{ $loop->iteration }}">
            <div class="custom-page-layout-stage__header">
                <span>{{ __('Layout') }} {{ $loop->iteration }} ({{ $layout['columns'] }} {{ $layout['columns'] === 1 ? __('column') : __('columns') }}, {{ $layout['rows'] }} {{ $layout['rows'] === 1 ? __('row') : __('rows') }})</span>
                <button class="admin-action" type="button"
                    wire:click="mountAction('editComponent', { componentIndex: {{ $layout['index'] }}, componentType: 'layout' })">
                    {{ __('Edit') }}
                </button>
            </div>
            <div class="custom-page-layout-stage__cells custom-page-layout-stage__cells--columns-{{ $layout['columns'] }} custom-page-layout-stage__cells--layout-{{ $layout['column_layout'] }}">
                @foreach ($layout['areas'] as $area)
                    <div class="custom-page-layout-stage__cell">
                        <button class="custom-page-layout-stage__add" type="button"
                            wire:click="mountAction('addComponent', { layoutAreaId: '{{ $area['id'] }}' })"
                            title="Add content to this Layout area">
                            <strong>{{ __('Row') }} {{ $area['row'] }}, {{ __('column') }} {{ $area['column'] }}</strong>
                            <span>{{ count($area['members']) }} {{ count($area['members']) === 1 ? __('component') : __('components') }}</span>
                        </button>
                        @foreach ($area['members'] as $member)
                            <button class="custom-page-layout-stage__member" type="button"
                                wire:click="mountAction('editComponent', { componentIndex: {{ $member['index'] }}, componentType: '{{ $member['type'] }}' })"
                                title="{{ $member['editable'] ? 'Edit' : 'Read-only' }} {{ $member['label'] }}"
                                @disabled(! $member['editable'])>
                                {{ $member['label'] }}
                            </button>
                        @endforeach
                        <button class="admin-action custom-page-layout-stage__border" type="button"
                            wire:click="mountAction('editLayoutArea', { gridIndex: {{ $layout['index'] }}, areaId: '{{ $area['id'] }}' })">
                            {{ __('Separator') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
