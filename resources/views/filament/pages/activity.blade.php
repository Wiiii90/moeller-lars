<x-filament-panels::page>
    <x-admin.workspace title="Activity">
        <x-admin.controls aria-label="Activity filters">
            <x-slot:filters>
                <div class="admin-data-control-group">
                    <span class="admin-data-control-label">Time range</span>
                    <x-admin.toolbar aria-label="Time range">
                        @foreach ($periodOptions as $value => $label)
                            <a
                                class="admin-action {{ $period === $value ? 'is-primary' : '' }}"
                                href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $value, 'area' => $area, 'family' => $family])) }}"
                            >{{ $label }}</a>
                        @endforeach
                    </x-admin.toolbar>
                </div>
                <div class="admin-data-control-group">
                    <span class="admin-data-control-label">Editorial area</span>
                    <x-admin.toolbar aria-label="Editorial area">
                        <a class="admin-action {{ $area === null ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'family' => $family])) }}">All areas</a>
                        @foreach ($areaOptions as $value => $label)
                            <a class="admin-action {{ $area === $value ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $value, 'family' => $family])) }}">{{ $label }}</a>
                        @endforeach
                    </x-admin.toolbar>
                </div>
                <div class="admin-data-control-group">
                    <span class="admin-data-control-label">Change type</span>
                    <x-admin.toolbar aria-label="Change type">
                        <a class="admin-action {{ $family === null ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $area])) }}">All changes</a>
                        @foreach ($familyOptions as $value => $label)
                            <a class="admin-action {{ $family === $value ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $area, 'family' => $value])) }}">{{ $label }}</a>
                        @endforeach
                    </x-admin.toolbar>
                </div>
            </x-slot:filters>
        </x-admin.controls>

        <x-admin.section kicker="History" title="Administrative activity">
            @if ($activity !== [])
                <x-admin.list aria-label="Administrative activity">
                    @foreach ($activity as $event)
                        <article class="admin-list__row">
                            <div class="admin-list__identity">
                                <span class="admin-list__eyebrow">{{ $event['area'] }}</span>
                                <strong>{{ $event['action'] }}</strong>
                                <span>{{ $event['target'] }} · {{ $event['actor'] }}</span>
                            </div>
                            <div class="admin-list__meta">
                                <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                            </div>
                            <div></div>
                            <x-admin.toolbar>
                                @if ($event['undo'] !== null)
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="undo({{ $event['undo']['id'] }})"
                                        wire:confirm="{{ $event['undo']['confirmation'] }}"
                                    >Undo</button>
                                @endif
                                @if ($event['url'] !== null)
                                    <a class="admin-action" href="{{ $event['url'] }}">Open</a>
                                @endif
                            </x-admin.toolbar>
                        </article>
                    @endforeach
                </x-admin.list>
            @else
                <x-admin.empty-state kicker="No matches" title="No activity matches these filters">
                    <p>Change the period, editorial area or change type to widen the activity scope.</p>
                </x-admin.empty-state>
            @endif
        </x-admin.section>

        @if ($paginator->hasPages())
            <footer class="admin-workspace__footnote">
                {{ $paginator->links() }}
            </footer>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
