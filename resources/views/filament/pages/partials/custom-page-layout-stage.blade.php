{{-- The existing Stage owns placements. Grid areas are not subordinate Pages. --}}
<div class="custom-page-layout-stage" aria-label="Custom Page layout">
    @foreach ($this->layoutStageGrids() as $grid)
        <section class="custom-page-layout-stage__grid" aria-label="Grid {{ $loop->iteration }}">
            <div class="custom-page-layout-stage__header">
                <span>{{ __('Grid') }} {{ $loop->iteration }} · {{ $grid['columns'] }} × {{ $grid['rows'] }}</span>
                <button class="admin-action" type="button"
                    wire:click="mountAction('editComponent', { componentIndex: {{ $grid['index'] }}, componentType: 'grid' })">
                    {{ __('Edit') }}
                </button>
            </div>
            <div class="custom-page-layout-stage__cells custom-page-layout-stage__cells--columns-{{ $grid['columns'] }}">
                @foreach ($grid['areas'] as $area)
                    <div class="custom-page-layout-stage__cell">
                        <button class="custom-page-layout-stage__add" type="button"
                            wire:click="mountAction('addComponent', { layoutAreaId: '{{ $area['id'] }}' })"
                            title="Add content to this Grid area">
                            <strong>{{ __('Row') }} {{ $area['row'] }} · {{ __('Column') }} {{ $area['column'] }}</strong>
                            <span>{{ $area['summary'] }}</span>
                        </button>
                        <button class="admin-action custom-page-layout-stage__border" type="button"
                            wire:click="mountAction('editGridArea', { gridIndex: {{ $grid['index'] }}, areaId: '{{ $area['id'] }}' })">
                            {{ __('Separator') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
