export function countValue(value) {
    if (!['number', 'string'].includes(typeof value) || (typeof value === 'string' && !/^\d+$/.test(value))) throw new RangeError('Analytics counts must be non-negative whole numbers.');
    const count = Number(value);
    if (!Number.isSafeInteger(count) || count < 0) throw new RangeError('Analytics counts must be non-negative whole numbers.');
    return count;
}

export function chartRows(months, series) {
    return months.map(month => ({...month, values: series.map(item => countValue(month[item.key])), total: series.reduce((sum, item) => sum + countValue(month[item.key]), 0)}));
}

export function countAxis(maximum) {
    const max = Math.max(1, countValue(maximum));
    const rough = max / 4;
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(1, rough)));
    const step = Math.max(1, [1, 2, 5, 10].find(value => value * magnitude >= rough) * magnitude);
    const upper = Math.ceil(max / step) * step;
    return {max: upper, ticks: Array.from({length: upper / step + 1}, (_, index) => index * step)};
}

export function chartTotals(rows, series) {
    return series.map((_, index) => rows.reduce((sum, row) => sum + row.values[index], 0));
}

export function periodRange(rows, from, to) {
    const start = Math.max(0, Math.min(rows.length - 1, Number(from)));
    const end = Math.max(start, Math.min(rows.length - 1, Number(to)));
    return rows.slice(start, end + 1);
}

export function internalRecordUrl(base, parameters, origin) {
    if (!base) return null;
    const url = new URL(base, origin);
    if (url.origin !== origin || !['http:', 'https:'].includes(url.protocol)) return null;
    Object.entries(parameters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') url.searchParams.set(key, String(value));
    });
    return url.href;
}
