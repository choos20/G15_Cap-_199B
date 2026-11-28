@props(['items' => [], 'overall' => [], 'showType' => true])

@once
    <link rel="stylesheet" href="{{ asset('css/evaluation-ratings.css') }}?v={{ substr(md5_file(public_path('css/evaluation-ratings.css')), 0, 12) }}">
    <script src="{{ asset('js/evaluation-ratings.js') }}?v={{ substr(md5_file(public_path('js/evaluation-ratings.js')), 0, 12) }}" defer></script>
@endonce

@php
    $ratings = collect($items)->map(function ($item) {
        $average = data_get($item, 'average');
        return [
            'label' => data_get($item, 'short_label', data_get($item, 'label', 'Evaluation area')),
            'type' => data_get($item, 'respondent_type'),
            'average' => is_numeric($average) ? (float) $average : null,
            'count' => data_get($item, 'count', data_get($item, 'ratings')),
            'legacy' => data_get($item, 'legacy', false),
        ];
    });
    $extremes = $ratings->filter(fn ($rating) => $rating['average'] !== null)->groupBy(fn ($rating) => $rating['type'] ?? 'all')->map(fn ($group) => ['min' => $group->min('average'), 'max' => $group->max('average'), 'count' => $group->count()]);
    $scale = \App\Models\EvaluationCriterion::SCALE;
@endphp

<div class="evaluation-ratings" data-evaluation-ratings>
    @if(count($overall))
        <div class="evaluation-ratings__summary" aria-label="Overall evaluation averages">
            @foreach($overall as $label => $value)
                <div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format((float) $value, 2).' / 5.00' : 'Not available' }}</strong></div>
            @endforeach
        </div>
    @endif
    <p class="evaluation-ratings__scale">Scale: @foreach($scale as $number => $label){{ $number }} {{ $label }}{{ !$loop->last ? ' · ' : '' }}@endforeach</p>
    @forelse($ratings as $rating)
        @php
            $score = $rating['average'];
            $percent = $score === null ? 0 : max(0, min(100, $score / 5 * 100));
            $typeLabel = $rating['type'] === 'implementer' ? 'Volunteer / implementer' : ($rating['type'] === 'beneficiary' ? 'Beneficiary' : null);
            $group = $extremes->get($rating['type'] ?? 'all');
            $highest = $score !== null && $group && $group['count'] > 1 && $group['max'] !== $group['min'] && $score === (float) $group['max'];
            $lowest = $score !== null && $group && $group['count'] > 1 && $group['max'] !== $group['min'] && $score === (float) $group['min'];
            $wholeLabel = $score !== null && $score === floor($score) ? ($scale[(int) $score] ?? null) : null;
            $info = $rating['label'].($typeLabel ? ' · '.$typeLabel : '').' · '.($score === null ? 'No ratings' : number_format($score, 2).' out of 5.00').(is_numeric($rating['count']) ? ' · '.$rating['count'].' ratings' : '');
        @endphp
        <div class="evaluation-ratings__row" title="{{ $info }}" tabindex="0" aria-label="{{ $info }}">
            <div class="evaluation-ratings__name"><span class="evaluation-ratings__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 19V11M12 19V5M19 19v-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></span><span><strong>{{ $rating['label'] }}</strong>@if($typeLabel && $showType)<small>{{ $typeLabel }}</small>@endif @if($rating['legacy'])<small>Legacy</small>@endif</span></div>
            <div class="evaluation-ratings__visual">
                <div class="evaluation-ratings__stars" aria-hidden="true"><span>★★★★★</span><span class="evaluation-ratings__stars-fill" style="--rating-width:{{ $percent }}%">★★★★★</span></div>
                <div class="evaluation-ratings__track" aria-hidden="true"><span style="--rating-width:{{ $percent }}%"></span></div>
            </div>
            <div class="evaluation-ratings__result"><strong>@if($score !== null)<span data-rating-value="{{ $score }}">{{ number_format($score, 2) }}</span> <small>/ 5.00</small>@else — @endif</strong><span>@if($highest)Highest @elseif($lowest)Lowest @elseif($wholeLabel){{ $wholeLabel }}@endif</span></div>
        </div>
    @empty
        <div class="evaluation-ratings__empty">No evaluation ratings are available for this period.</div>
    @endforelse
</div>
