<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="admin-partial-date"
        x-data="adminPartialDateControl($wire.entangle('{{ $getStatePath() }}'), @js($region))"
        x-on:keydown.escape.window="close()"
        x-on:resize.window="reposition()"
        x-on:scroll.window="reposition()"
    >
        <button type="button"
            class="admin-partial-date__trigger"
            x-ref="trigger"
            x-on:click="toggle()"
            x-bind:aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="{{ $accessibleLabel }}: choose year, month or exact date"
        >
            <span x-text="display"></span>
            <x-filament::icon icon="heroicon-o-calendar-days" class="admin-partial-date__icon" />
        </button>

        <template x-teleport="body">
            <section class="admin-partial-date__popover"
                x-cloak x-show="open"
                x-bind:style="position"
                role="dialog"
                aria-label="{{ $accessibleLabel }} date picker"
                x-on:click.outside="if (!$event.target.closest('.admin-partial-date__trigger')) close()"
            >
                <div class="admin-partial-date__modes" aria-label="Date precision">
                    <button type="button" x-on:click="chooseMode('year')" x-bind:aria-pressed="mode === 'year'">Year</button>
                    <button type="button" x-on:click="chooseMode('month')" x-bind:aria-pressed="mode === 'month'">Month / year</button>
                    <button type="button" x-on:click="chooseMode('day')" x-bind:aria-pressed="mode === 'day'">Exact date</button>
                </div>

                <div class="admin-partial-date__navigation">
                    <button type="button" aria-label="Previous period" x-on:click="shift(-1)">‹</button>
                    <select x-show="mode === 'day'" x-model.number="month" aria-label="Month">
                        <template x-for="(name, index) in monthNames" :key="index">
                            <option x-bind:value="index + 1" x-text="name"></option>
                        </template>
                    </select>
                    <input type="number" min="1000" max="9999" step="1" x-model.number="year"
                        x-on:change="normalizeYear()" aria-label="Year to browse">
                    <button type="button" aria-label="Next period" x-on:click="shift(1)">›</button>
                </div>

                <div class="admin-partial-date__choices" x-show="mode === 'year'">
                    <template x-for="candidate in years" :key="candidate">
                        <button type="button" x-text="candidate" x-on:click="chooseYear(candidate)"
                            x-bind:aria-pressed="value === String(candidate)"></button>
                    </template>
                </div>
                <div class="admin-partial-date__choices" x-show="mode === 'month'">
                    <template x-for="(name, index) in monthNames" :key="index">
                        <button type="button" x-text="name" x-on:click="chooseMonth(index + 1)"
                            x-bind:aria-pressed="value === String(year) + '-' + String(index + 1).padStart(2, '0')"></button>
                    </template>
                </div>
                <div class="admin-partial-date__calendar" x-show="mode === 'day'">
                    <template x-for="(name, index) in weekdays" :key="index">
                        <span class="admin-partial-date__weekday" x-text="name"></span>
                    </template>
                    <template x-for="cell in calendarDays" :key="cell.key">
                        <button type="button" x-text="cell.day ?? ''" x-bind:disabled="!cell.day"
                            x-bind:aria-pressed="cell.day ? isSelected(cell.day) : false"
                            x-on:click="chooseDay(cell.day)"></button>
                    </template>
                </div>

                <div class="admin-partial-date__footer">
                    <button type="button" x-on:click="clear()" x-bind:disabled="!value">Clear date</button>
                </div>
            </section>
        </template>
    </div>
</x-dynamic-component>
