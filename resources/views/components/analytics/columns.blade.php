@props(['title', 'description' => '', 'data', 'series', 'unit' => 'proposals', 'breakdownLabel' => 'By status', 'mode' => 'stacked', 'recordUrl' => null])
@php
    $chartId = 'analytics-'.\Illuminate\Support\Str::uuid();
    $months = collect($data)->values()->map(function ($month, $index) use ($data) {
        $date = now()->startOfMonth()->subMonths(count($data) - 1 - $index);
        return array_merge($month, ['period' => $month['key'] ?? $date->format('Y-m'), 'periodLabel' => $date->format('M Y')]);
    })->all();
    $chartTotal = collect($months)->sum(fn ($month) => collect($series)->sum(fn ($item) => $month[$item['key']]));
    $singularUnit = \Illuminate\Support\Str::singular($unit);
    $config = compact('months', 'series', 'unit', 'singularUnit', 'mode', 'recordUrl');
@endphp
<x-analytics.assets />
<section {{ $attributes->class(['pl-analytics', 'pl-columns-card']) }} data-analytics-columns data-analytics-state="loading" aria-busy="true" aria-labelledby="{{ $chartId }}-title">
    <header class="pl-analytics-header">
        <div class="pl-analytics-heading"><span class="pl-analytics-icon" aria-hidden="true"><x-ui-icon name="chart" :size="21" /></span><div><h3 id="{{ $chartId }}-title">{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div></div>
        <details class="pl-period" data-analytics-period>
            <summary aria-label="Choose chart date range"><x-ui-icon name="calendar" :size="15" /><span data-period-label>{{ $months[0]['periodLabel'] ?? 'No dates' }} – {{ $months[count($months) - 1]['periodLabel'] ?? '' }}</span><x-ui-icon name="chevron-down" :size="13" /></summary>
            <div class="pl-period-panel">
                <strong>Chart date range</strong><p>Choose from the available months.</p>
                <div class="pl-period-fields"><label>From<select data-period-from disabled>@foreach($months as $index => $month)<option value="{{ $index }}">{{ $month['periodLabel'] }}</option>@endforeach</select></label><label>To<select data-period-to disabled>@foreach($months as $index => $month)<option value="{{ $index }}" @selected($loop->last)>{{ $month['periodLabel'] }}</option>@endforeach</select></label></div>
                <div class="pl-period-presets"><button type="button" data-period-preset="1" disabled>This month</button><button type="button" data-period-preset="3" disabled>Last 3 months</button><button type="button" data-period-preset="all" disabled>All {{ count($months) }} months</button></div>
            </div>
        </details>
    </header>
    <div class="pl-chart-summary"><div><span class="pl-chart-eyebrow">{{ ucfirst($unit) }} in selected period</span><strong data-period-total>{{ $chartTotal }}</strong></div>@if(count($series) > 1)<div class="pl-segmented" role="group" aria-label="Chart display"><button type="button" data-chart-mode="{{ $mode }}" aria-pressed="true" disabled>{{ $breakdownLabel }}</button><button type="button" data-chart-mode="total" aria-pressed="false" disabled>Total</button></div>@endif</div>
    <div class="pl-chart-canvas" data-chart-canvas>
        <div class="pl-chart-skeleton" data-chart-skeleton role="status"><span class="pl-sr-only">Loading chart…</span>@foreach(range(1, 6) as $index)<i></i>@endforeach</div>
    </div>
    <p class="pl-chart-empty" data-chart-empty hidden>No {{ $unit }} recorded in this period.</p>
    <p class="pl-chart-error" data-chart-error hidden>The chart could not be displayed. Its data table is available below.</p>
    <div class="pl-chart-legend" data-chart-legend aria-label="Chart legend">
        @foreach($series as $item)<div><span class="pl-legend-label">{{ $item['label'] }}</span><strong>{{ collect($months)->sum($item['key']) }}</strong><small>{{ \Illuminate\Support\Str::plural($singularUnit, collect($months)->sum($item['key'])) }}</small></div>@endforeach
    </div>
    {{ $slot }}
    <p class="pl-sr-only" data-chart-announcement role="status" aria-live="polite"></p>
    <details class="pl-chart-data"><summary>View data table</summary><div class="pl-chart-table-scroll"><table data-chart-table><caption>{{ $title }} — {{ $unit }}</caption><thead><tr><th scope="col">Month</th>@foreach($series as $item)<th scope="col">{{ $item['label'] }}</th>@endforeach<th scope="col">Total</th></tr></thead><tbody>@foreach($months as $month)<tr><th scope="row">{{ $month['periodLabel'] }}</th>@foreach($series as $item)<td>{{ $month[$item['key']] }}</td>@endforeach<td>{{ collect($series)->sum(fn ($item) => $month[$item['key']]) }}</td></tr>@endforeach</tbody></table></div></details>
    <noscript><p class="pl-chart-empty">Enable JavaScript for interactive charts, or expand the data table above.</p><style>.pl-chart-skeleton{display:none!important}</style></noscript>
    <script type="application/json" data-analytics-config>{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</section>
