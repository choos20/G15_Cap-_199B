import test from 'node:test';
import assert from 'node:assert/strict';
import {chartRows, chartTotals, countAxis, countValue, internalRecordUrl, periodRange} from '../../resources/js/analytics-data.js';

const series = [{key: 'approved'}, {key: 'pending'}, {key: 'returned'}];
const months = [
    {period: '2026-09', approved: 1, pending: 3, returned: 0},
    {period: '2026-10', approved: 0, pending: 0, returned: 0},
    {period: '2026-11', approved: 2, pending: 1, returned: 1},
];

test('counts reject invalid, fractional, negative, and unsafe values', () => {
    for (const value of [undefined, null, true, '', '1.5', '-1', NaN, Infinity, -1, 0.5, Number.MAX_SAFE_INTEGER + 1]) {
        assert.throws(() => countValue(value), RangeError);
    }
    assert.equal(countValue('012'), 12);
    assert.equal(countValue(0), 0);
});

test('stacked, grouped, and total views retain the supplied counts', () => {
    const rows = chartRows(months, series);
    assert.deepEqual(rows.map(row => row.total), [4, 0, 4]);
    assert.deepEqual(chartTotals(rows, series), [3, 4, 1]);
    assert.deepEqual(months[0], {period: '2026-09', approved: 1, pending: 3, returned: 0});
    assert.deepEqual(rows[2].values, [2, 1, 1]);
});

test('period filters select only available months and keep their exact totals', () => {
    const rows = chartRows(months, series);
    assert.deepEqual(chartTotals(periodRange(rows, '1', '2'), series), [2, 1, 1]);
    assert.deepEqual(periodRange(rows, '2', '0').map(row => row.period), ['2026-11']);
    assert.deepEqual(periodRange([], '0', '0'), []);
});

test('count axes always include zero and integer ticks without clipping data', () => {
    for (const count of [0, 1, 2, 3, 5, 9, 21, 100, 101, 1000, 12345]) {
        const axis = countAxis(count);
        assert.equal(axis.ticks[0], 0);
        assert.ok(axis.max >= count && axis.max > 0);
        assert.ok(axis.ticks.every(tick => Number.isInteger(tick) && tick >= 0));
        assert.ok(axis.ticks.length <= 6);
        assert.equal(axis.ticks.at(-1), axis.max);
    }
});

test('drill-downs preserve scope and encode dates in same-origin record links', () => {
    const origin = 'https://projectlink.example';
    const link = internalRecordUrl('/projects/all?initiators=1', {from: '2026-11', to: '2026-11'}, origin);
    assert.equal(link, origin + '/projects/all?initiators=1&from=2026-11&to=2026-11');
    for (const link of ['https://external.example/file', '//external.example/file', 'javascript:alert(1)', 'data:text/plain,test', null]) {
        assert.equal(internalRecordUrl(link, {}, origin), null);
    }
});
