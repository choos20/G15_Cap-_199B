@props(['stages', 'total', 'links' => []])
<x-analytics.assets />
<div {{ $attributes->class(['pl-pipeline']) }} data-analytics-pipeline>
    @foreach($stages as $stage)
        @php
            $percent = $total > 0 ? $stage['count'] / $total * 100 : 0;
            $href = $links[$stage['key']] ?? null;
            $icon = ['secretary' => 'file', 'coordinator' => 'clipboard', 'celp_head' => 'check-circle', 'csa' => 'shield'][$stage['key']] ?? 'file';
        @endphp
        <{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="pl-pipeline-step" style="--item-color:{{ $stage['color'] }}" title="{{ $stage['label'] }}: {{ $stage['count'] }} proposals; {{ round($percent, 1) }}% of proposals in review">
            <span class="pl-pipeline-icon" aria-hidden="true"><x-ui-icon :name="$icon" :size="19" /></span><span class="pl-pipeline-name">{{ $stage['label'] }}</span><strong data-count-up="{{ $stage['count'] }}" aria-label="{{ $stage['count'] }}">{{ $stage['count'] }}</strong><span class="pl-mini-track" aria-hidden="true"><i style="width:{{ $percent }}%"></i></span><small>{{ round($percent) }}% <span>of in-review proposals</span></small>
        </{{ $href ? 'a' : 'div' }}>
    @endforeach
</div>
