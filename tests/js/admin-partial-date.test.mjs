import test from 'node:test';
import assert from 'node:assert/strict';

// The canonical Alpine date controller uses window only for rendering and
// positioning; Node can exercise its date and change contracts without a DOM.
globalThis.window ??= { innerWidth: 1280, innerHeight: 800 };
const { adminPartialDateControl } = await import('../../resources/js/admin-partial-date.js');

function editor(value, region = 'de-DE') {
    const events = [];
    const picker = adminPartialDateControl(value, region);
    picker.$refs = {
        trigger: {
            dispatchEvent(event) { events.push(event.type); },
            getBoundingClientRect() {
                return { left: 80, right: 160, top: 120, bottom: 150 };
            },
        },
    };
    picker.$nextTick = fn => fn();
    picker.$watch = () => {};
    picker.init();
    return { picker, events };
}

test('the same control round-trips year, month and exact day and emits one real change', () => {
    const { picker, events } = editor('2027-06');
    assert.equal(picker.mode, 'month');
    assert.equal(picker.display, '06.2027');

    picker.chooseYear(2027);
    assert.equal(picker.value, '2027');
    assert.deepEqual(events, ['change']);
    picker.chooseYear(2027);
    assert.deepEqual(events, ['change'], 'Selecting an unchanged year must not autosave again');

    picker.year = 2028;
    picker.chooseMonth(2);
    assert.equal(picker.value, '2028-02');
    picker.chooseDay(29);
    assert.equal(picker.value, '2028-02-29');
    assert.equal(picker.display, '29.02.2028');
    assert.deepEqual(events, ['change', 'change', 'change']);

    picker.clear();
    assert.equal(picker.value, null);
    assert.equal(picker.display, 'Choose date');
    assert.equal(events.length, 4);
    picker.clear();
    assert.equal(events.length, 4);
});

test('date selection and regional formatting share one canonical ISO value', () => {
    for (const [region, expected] of [
        ['de-DE', '17.06.2027'],
        ['en-GB', '17/06/2027'],
        ['en-US', '06/17/2027'],
    ]) {
        const { picker, events } = editor('2027-06-17', region);
        assert.equal(picker.value, '2027-06-17');
        assert.equal(picker.display, expected);
        assert.equal(picker.mode, 'day');
        assert.equal(picker.isSelected(17), true);
        assert.equal(picker.isSelected(18), false);
        assert.deepEqual(events, []);
    }
});

test('calendar and navigation handle leap day and year boundaries without guessing dates', () => {
    const { picker } = editor('2028-02');
    picker.mode = 'day';
    assert.ok(picker.calendarDays.some(cell => cell.day === 29));
    assert.ok(!picker.calendarDays.some(cell => cell.day === 30));

    picker.year = 2027;
    assert.ok(!picker.calendarDays.some(cell => cell.day === 29));
    picker.year = 2028;
    picker.month = 12;
    picker.shift(1);
    assert.equal(picker.year, 2029);
    assert.equal(picker.month, 1);
    picker.shift(-1);
    assert.equal(picker.year, 2028);
    assert.equal(picker.month, 12);
});
