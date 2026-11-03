// One central picker for year, month/year and exact-date precision.
// Stored state is ISO YYYY, YYYY-MM or YYYY-MM-DD; regional formatting is display-only.
const REGIONS = new Set(['de-DE', 'en-GB', 'en-US']);

function parsedDate(value) {
    const match = /^([0-9]{4})(?:-([0-9]{2})(?:-([0-9]{2}))?)?$/.exec(String(value ?? ''));
    if (!match) return null;
    return { year: Number(match[1]), month: match[2] ? Number(match[2]) : null, day: match[3] ? Number(match[3]) : null };
}

function formatDate(value, locale) {
    const parsed = parsedDate(value);
    if (!parsed) return 'Choose date';
    if (parsed.month === null) return String(parsed.year);
    const date = new Date(Date.UTC(parsed.year, parsed.month - 1, parsed.day ?? 1));
    const options = parsed.day === null
        ? { year: 'numeric', month: '2-digit', timeZone: 'UTC' }
        : { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'UTC' };
    return new Intl.DateTimeFormat(locale, options).format(date);
}

function pad(value) {
    return String(value).padStart(2, '0');
}

export function adminPartialDateControl(entangledValue, preferredRegion) {
    return {
        value: entangledValue,
        region: REGIONS.has(preferredRegion) ? preferredRegion : 'de-DE',
        open: false,
        mode: 'year',
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1,
        position: { left: 8, top: 8 },
        get display() { return formatDate(this.value, this.region); },
        get years() {
            const first = Math.floor(this.year / 12) * 12;
            return Array.from({ length: 12 }, (_, index) => first + index);
        },
        get monthNames() {
            return Array.from({ length: 12 }, (_, index) => new Intl.DateTimeFormat(this.region, { month: 'short', timeZone: 'UTC' })
                .format(new Date(Date.UTC(2020, index, 1))));
        },
        get weekdays() {
            const monday = Date.UTC(2020, 5, 1);
            return Array.from({ length: 7 }, (_, index) =>
                new Intl.DateTimeFormat(this.region, { weekday: 'short', timeZone: 'UTC' })
                    .format(new Date(monday + index * 86400000)));
        },
        get calendarDays() {
            const firstWeekday = (new Date(Date.UTC(this.year, this.month - 1, 1)).getUTCDay() + 6) % 7;
            const total = new Date(Date.UTC(this.year, this.month, 0)).getUTCDate();
            const length = Math.ceil((firstWeekday + total) / 7) * 7;
            return Array.from({ length }, (_, index) => ({
                key: index,
                day: index >= firstWeekday && index < firstWeekday + total ? index - firstWeekday + 1 : null,
            }));
        },
        init() {
            this.readValue();
            this.$watch('value', () => { if (!this.open) this.readValue(); });
        },
        readValue() {
            const parsed = parsedDate(this.value);
            if (!parsed) return;
            this.year = parsed.year;
            this.month = parsed.month ?? 1;
            this.mode = parsed.month === null ? 'year' : (parsed.day === null ? 'month' : 'day');
        },
        normalizeYear() {
            this.year = Math.min(9999, Math.max(1000, Number(this.year) || new Date().getFullYear()));
        },
        reposition() {
            if (!this.open) return;
            const rect = this.$refs.trigger.getBoundingClientRect();
            const width = Math.min(340, Math.max(260, window.innerWidth - 16));
            this.position = {
                left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
                top: rect.bottom + 352 <= window.innerHeight
                    ? rect.bottom + 5
                    : Math.max(8, rect.top - 352),
            };
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.readValue();
                this.$nextTick(() => this.reposition());
            }
        },
        close() { this.open = false; },
        chooseMode(mode) {
            this.mode = mode;
            this.reposition();
        },
        shift(direction) {
            if (this.mode === 'year') {
                this.year = Math.min(9999, Math.max(1000, this.year + direction * 12));
            } else if (this.mode === 'month') {
                this.year = Math.min(9999, Math.max(1000, this.year + direction));
            } else {
                const next = new Date(Date.UTC(this.year, this.month - 1 + direction, 1));
                this.year = next.getUTCFullYear();
                this.month = next.getUTCMonth() + 1;
            }
        },
        commit(value) {
            if (this.value === value) {
                this.close();
                return;
            }
            this.value = value;
            this.close();
            // The Edit dialog's existing native-change owner persists this
            // single field. Do not introduce an independent autosave action.
            this.$nextTick(() => {
                this.$refs.trigger.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
        chooseYear(year) {
            this.year = year;
            this.commit(String(year));
        },
        chooseMonth(month) {
            this.month = month;
            this.commit(String(this.year) + '-' + pad(month));
        },
        chooseDay(day) {
            if (day === null) return;
            this.commit(String(this.year) + '-' + pad(this.month) + '-' + pad(day));
        },
        clear() { this.commit(null); },
        isSelected(day) {
            const selected = parsedDate(this.value);
            return selected?.day === day && selected?.year === this.year && selected?.month === this.month;
        },
    };
}

window.adminPartialDateControl = adminPartialDateControl;
