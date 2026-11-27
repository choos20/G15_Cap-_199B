@props(['title', 'description' => '', 'items', 'totalLabel' => 'Proposals', 'links' => [], 'pipeline' => false])
@php
    $chartId = 'radial-'.\Illuminate\Support\Str::uuid();
    $ringItems = collect($items)->values()->map(fn ($item, $index) => array_merge($item, ['key' => $item['key'] ?? (string) $index, 'color' => $item['color'] ?? $item['fill'] ?? '#087c5b']))->all();
    $total = array_sum(array_column($ringItems, 'count'));
@endphp
<x-analytics.assets />
<section {{ $attributes->class(['pl-analytics', 'pl-radial-card', 'pl-radial-pipeline' => $pipeline]) }} data-analytics-radial aria-labelledby="{{ $chartId }}-title">
    <header class="pl-analytics-header"><div class="pl-analytics-heading"><span class="pl-analytics-icon" aria-hidden="true"><x-ui-icon name="chart" :size="21" /></span><div><h3 id="{{ $chartId }}-title">{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div></div></header>
    <div class="pl-radial-layout">
        <div class="pl-radial-visual"><svg viewBox="0 0 250 250" class="pl-radial-svg" role="img" aria-label="{{ $title }}: {{ collect($ringItems)->map(fn ($item) => $item['label'].' '.$item['count'])->implode(', ') }}">
            <defs>@foreach($ringItems as $item)<linearGradient id="{{ $chartId }}-{{ $loop->index }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="{{ $item['color'] }}" stop-opacity=".55" /><stop offset="100%" stop-color="{{ $item['color'] }}" /></linearGradient>@endforeach</defs>
            @foreach($ringItems as $item)
                @php
                    $radius = 105 - ($loop->index * min(17, 65 / max(count($ringItems) - 1, 1)));
                    $circumference = 2 * M_PI * $radius;
                    $percent = $total > 0 ? $item['count'] / $total * 100 : 0;
                    $href = $links[$item['key']] ?? null;
                @endphp
                <circle class="pl-ring-track" cx="125" cy="125" r="{{ $radius }}" stroke-width="{{ count($ringItems) > 4 ? 9 : 12 }}" />
                @if($href)<a href="{{ $href }}" aria-label="{{ $item['label'] }}: {{ $item['count'] }} ({{ round($percent) }}%). View records">@endif
                <g data-radial-item="{{ $loop->index }}" tabindex="{{ $href ? '-1' : '0' }}" data-tooltip-title="{{ $item['label'] }}" data-tooltip-value="{{ $item['count'] }}" data-tooltip-percent="{{ round($percent, 1) }}" aria-label="{{ $item['label'] }}: {{ $item['count'] }}, {{ round($percent, 1) }} percent">
                    @if($item['count'] > 0)<circle class="pl-ring-value" cx="125" cy="125" r="{{ $radius }}" stroke="url(#{{ $chartId }}-{{ $loop->index }})" stroke-width="{{ count($ringItems) > 4 ? 9 : 12 }}" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $percent / 100) }}" transform="rotate(-90 125 125)" style="--ring-length:{{ $circumference }};--ring-offset:{{ $circumference * (1 - $percent / 100) }}" />@endif
                    <title>{{ $item['label'] }}: {{ $item['count'] }} ({{ round($percent, 1) }}%)</title>
                </g>
                @if($href)</a>@endif
            @endforeach
            <text class="pl-ring-total" x="125" y="124" data-count-up="{{ $total }}" aria-label="{{ $total }}">{{ $total }}</text><text class="pl-ring-caption" x="125" y="145">{{ $totalLabel }}</text>
        </svg></div>
        <div class="pl-radial-legend" aria-label="{{ $title }} legend">
            @foreach($ringItems as $item)
                @php $percent = $total > 0 ? $item['count'] / $total * 100 : 0; $href = $links[$item['key']] ?? null; @endphp
                <{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="pl-radial-row" data-radial-legend="{{ $loop->index }}" style="--item-color:{{ $item['color'] }}">
                    <span class="pl-legend-dot" aria-hidden="true"></span><span class="pl-radial-name">{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong><small>{{ round($percent) }}%</small>
                    @if($pipeline)<span class="pl-mini-track" aria-hidden="true"><i style="width:{{ $percent }}%"></i></span>@endif
                </{{ $href ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
    @if($total === 0)<p class="pl-chart-empty">No {{ strtolower($totalLabel) }} recorded yet.</p>@else<p class="pl-chart-footnote">Each ring shows its share of {{ $total }} {{ strtolower($totalLabel) }}.</p>@endif
    {{ $slot }}
</section>
