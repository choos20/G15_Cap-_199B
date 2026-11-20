@extends('layouts.admin')

@section('title', 'Evaluation summary')
@section('subtitle', $announcement->title)

@section('content')
    <style>
        .su-crumbs { font-size:0.85rem; color:#94a3b8; margin-bottom:14px; }
        .su-crumbs a { color:#0b6a4a; text-decoration:none; font-weight:600; }
        .su-crumbs a:hover { text-decoration:underline; }

        .su-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; }
        .su-tile { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:22px; }
        .su-tile h4 { margin:0 0 10px; font-size:0.87rem; color:#64748b; font-weight:700; }
        .su-big { font-size:2.2rem; font-weight:800; color:#0f172a; line-height:1; }
        .su-sub { font-size:0.85rem; color:#94a3b8; margin-top:5px; }

        .su-src { display:flex; align-items:center; gap:9px; padding:4px 0; font-size:0.88rem; color:#475569; }
        .su-src i { width:11px; height:11px; border-radius:3px; display:inline-block; flex-shrink:0; }
        .su-src b { margin-left:auto; color:#0f172a; font-variant-numeric:tabular-nums; }

        .su-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:24px; margin-top:18px; }
        .su-card h3 { margin:0 0 4px; font-size:1.05rem; color:#0f172a; }
        .su-card .hint { font-size:0.85rem; color:#94a3b8; margin-bottom:18px; }

        .su-row { display:grid; grid-template-columns:minmax(140px,240px) 1fr 46px; gap:14px; align-items:center; padding:7px 0; }
        .su-row .nm { font-size:0.9rem; color:#334155; }
        .su-track { height:13px; border-radius:4px; background:#f1f5f9; overflow:hidden; }
        .su-track i { display:block; height:100%; background:#0f7a52; border-radius:4px; }
        .su-val { text-align:right; font-weight:700; color:#0f172a; font-variant-numeric:tabular-nums; font-size:0.9rem; }
        .su-axis { display:flex; justify-content:space-between; margin-top:8px; padding-left:calc(min(240px,25%) + 14px);
                   padding-right:60px; font-size:0.78rem; color:#cbd5e1; }

        .su-two { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:18px; }
        @media (max-width: 860px) { .su-two { grid-template-columns:1fr; } }
        .su-quote { border-left:3px solid #e2e8f0; padding:4px 0 4px 14px; margin-bottom:14px; color:#475569; font-size:0.9rem; }
        .su-more { color:#0b6a4a; font-weight:700; font-size:0.86rem; background:none; border:none; cursor:pointer; padding:0; }
        .su-more:hover { text-decoration:underline; }

        .su-note { margin-top:18px; display:flex; gap:10px; align-items:flex-start; padding:14px 18px; border-radius:14px;
                   background:#f8fafc; border:1px solid #e2e8f0; color:#475569; font-size:0.87rem; }
    </style>

    @php
        $target = $evaluation->expected_beneficiaries;
        $respondentLabels = [
            'beneficiary' => 'Beneficiary',
            'implementer' => 'Student Volunteer / Implementer',
        ];
    @endphp

    <div class="su-crumbs">
        <a href="{{ route('student_org.evaluations') }}">Evaluations</a> &rsaquo; Evaluation summary
    </div>

    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:18px">
        <h2 style="margin:0; font-size:1.5rem; color:#0f172a">{{ $announcement->title }}</h2>
        @if($canEncode && ! $evaluation->isSubmitted())
            <a href="{{ route('evaluations.encode', $announcement) }}"
               style="margin-left:auto; padding:10px 16px; border-radius:12px; background:#0b6a4a; color:#fff; font-weight:700; text-decoration:none; font-size:0.9rem">
                Continue encoding
            </a>
        @elseif($canEncode && $evaluation->isSubmitted())
            <a href="#reopen-form"
               style="margin-left:auto; padding:10px 16px; border-radius:12px; background:#0b6a4a; color:#fff; font-weight:700; text-decoration:none; font-size:0.9rem">
                Edit submitted evaluation
            </a>
        @endif
    </div>

    @if($canEncode && $evaluation->isSubmitted())
        <div id="reopen-form" class="su-card" style="margin-bottom:18px; border-color:#a7f3d0; background:#f0fdf4">
            <h3>Edit submitted evaluation</h3>
            <p class="hint">The existing responses will be kept. Reopen this batch to correct a response or add missing forms, then resubmit it.</p>
            <form method="POST" action="{{ route('evaluations.reopen', $announcement) }}">
                @csrf
                <label for="reopen-reason">Reason for correction</label>
                <textarea id="reopen-reason" name="reason" required maxlength="500" rows="3" style="display:block;width:100%;margin:8px 0 12px;padding:10px;border:1px solid #cbd5e1;border-radius:8px">{{ old('reason') }}</textarea>
                <button type="submit" style="padding:10px 16px;border:0;border-radius:10px;background:#0b6a4a;color:white;font-weight:700;cursor:pointer">Reopen for correction</button>
            </form>
        </div>
    @endif

    @if(session('status'))
        <div style="margin-bottom:16px; padding:14px 18px; border-radius:12px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-weight:600">
            {{ session('status') }}
        </div>
    @endif

    @if($evaluation->correction_in_progress)
        <div class="su-note" role="status"><strong>Reopened for correction.</strong> These results are being updated and have not yet been resubmitted.</div>
    @endif
    @if($canEncode && ! $evaluation->isSubmitted() && $submissionWarning)
        <div class="su-note" role="alert" style="border-color:#fed7aa; background:#fff7ed; color:#9a3412">
            <strong>Before submitting:</strong> {{ $submissionWarning }} Add the missing forms, or confirm below if this batch is intentionally incomplete.
        </div>
    @endif
    @if($errors->any())
        <div class="su-note" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="su-tiles">
        <div class="su-tile">
            <h4>Beneficiary responses</h4>
            <div class="su-big">{{ $responsesByType->get('beneficiary', collect())->count() }}</div>
            <div class="su-sub">@if($evaluation->paper_beneficiary_count !== null) of {{ $evaluation->paper_beneficiary_count }} paper forms received @else forms encoded @endif @if($target) · {{ $target }} beneficiaries attended @endif</div>
        </div>
        <div class="su-tile">
            <h4>Volunteer / implementer responses</h4>
            <div class="su-big">{{ $responsesByType->get('implementer', collect())->count() }}</div>
            <div class="su-sub">@if($evaluation->paper_implementer_count !== null) of {{ $evaluation->paper_implementer_count }} paper forms received @else forms encoded @endif</div>
        </div>
        <div class="su-tile">
            <h4>Total responses</h4>
            <div class="su-big">{{ $responseCount }}</div>
            <div class="su-sub">all respondent types</div>
        </div>
    </div>

    @foreach($respondentLabels as $type => $label)
        @php
            $typeResponses = $responsesByType->get($type, collect());
            $typeCriteria = $criteria->filter(fn ($criterion) => $criterion->respondent_type === $type);
        @endphp
        <div class="su-card">
            <h3>{{ $label }} ratings</h3>
            <div class="hint">{{ $typeResponses->count() }} {{ Str::plural('response', $typeResponses->count()) }} &middot; scale: 1 (Poor) to 5 (Excellent) &middot;
                average {{ isset($overallByType[$type]) ? number_format($overallByType[$type], 2).' / 5' : 'not available' }}
            </div>
            @if($typeResponses->isEmpty())
                <div style="padding:24px 0; color:#94a3b8">No {{ strtolower($label) }} forms have been encoded yet.</div>
            @else
                @foreach($typeCriteria as $criterion)
                    @php $avg = $averages[$type][$criterion->id] ?? null; @endphp
                    <div class="su-row">
                        <div class="nm">
                            {{ $criterion->short_label }}
                            @unless($criterion->is_active)
                                <span style="font-size:0.76rem; color:#94a3b8">(legacy)</span>
                            @endunless
                        </div>
                        <div class="su-track"><i style="width:{{ $avg ? ($avg / 5 * 100) : 0 }}%"></i></div>
                        <div class="su-val">{{ $avg ? number_format($avg, 2) : '—' }}</div>
                    </div>
                @endforeach
                <div class="su-axis"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>
            @endif
        </div>
    @endforeach

    @if($customQuestions->isNotEmpty())
        <div class="su-card">
            <h3>Additional question results</h3>
            <div class="hint">Answers to questions added for this activity</div>
            @foreach($customQuestions as $question)
                @php $answers = $question->answers->filter(fn ($answer) => filled($answer->answer)); @endphp
                <div style="padding:14px 0; border-top:1px solid #f1f5f9">
                    <strong style="color:#334155">{{ $question->question }}</strong>
                    <span style="color:#94a3b8; font-size:.8rem"> · {{ $respondentLabels[$question->respondent_type] ?? ucfirst($question->respondent_type) }} · {{ $answers->count() }} answers</span>
                    @if($question->question_type === 'rating')
                        @php $average = $answers->avg(fn ($answer) => (float) $answer->answer); @endphp
                        <div style="margin-top:7px; color:#0f172a; font-weight:700">{{ $average !== null ? number_format($average, 2).' / 5 average' : 'No ratings' }}</div>
                    @elseif(in_array($question->question_type, ['yes_no_maybe', 'multiple_choice'], true))
                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px">
                            @foreach($answers->countBy(fn ($item) => $item->answer) as $answer => $count)
                                <span class="su-src" style="padding:6px 10px; border:1px solid #e2e8f0; border-radius:999px">{{ $answer }} <b>{{ $count }}</b></span>
                            @endforeach
                        </div>
                    @else
                        @forelse($answers as $answer)
                            <div class="su-quote" style="margin-top:8px">{{ $answer->answer }}</div>
                        @empty
                            <div style="margin-top:7px; color:#94a3b8">No written answers.</div>
                        @endforelse
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="su-card">
        <h3>Interest in volunteering again</h3>
        <div class="su-two" style="margin-top:0">
            @foreach($respondentLabels as $type => $label)
                <div>
                    <strong>{{ $label }}</strong>
                    @forelse($volunteerInterestCounts[$type] ?? [] as $answer => $count)
                        <div class="su-src">{{ ucfirst($answer) }} <b>{{ $count }}</b></div>
                    @empty
                        <div style="margin-top:7px; color:#94a3b8; font-size:.88rem">No answers recorded.</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>

    <div class="su-card">
        <h3>How respondents heard about the activity</h3>
        <div class="hint">Respondents could select more than one source</div>
        @forelse($sourceCounts as $source => $count)
            <div class="su-src">{{ $heardAboutLabels[$source] ?? ucfirst(str_replace('_', ' ', $source)) }} <b>{{ $count }}</b></div>
        @empty
            <div style="color:#94a3b8; font-size:.9rem">No responses recorded.</div>
        @endforelse
    </div>

    <div class="su-two">
        <div class="su-card" style="margin-top:0">
            <h3>Suggestions</h3>
            <div class="hint">{{ $suggestions->count() }} of {{ $responseCount }} responded</div>
            @forelse($suggestions->take(3) as $text)
                <div class="su-quote">{{ $text }}</div>
            @empty
                <div style="color:#94a3b8; font-size:0.9rem">No suggestions were recorded.</div>
            @endforelse
            @if($suggestions->count() > 3)
                <div id="more-suggestions" style="display:none">
                    @foreach($suggestions->slice(3) as $text)
                        <div class="su-quote">{{ $text }}</div>
                    @endforeach
                </div>
                <button type="button" class="su-more" data-reveal="more-suggestions">Show all {{ $suggestions->count() }} suggestions</button>
            @endif
        </div>

        <div class="su-card" style="margin-top:0">
            <h3>Observations</h3>
            <div class="hint">{{ $observations->count() }} of {{ $responseCount }} responded</div>
            @forelse($observations->take(3) as $text)
                <div class="su-quote">{{ $text }}</div>
            @empty
                <div style="color:#94a3b8; font-size:0.9rem">No observations were recorded.</div>
            @endforelse
            @if($observations->count() > 3)
                <div id="more-observations" style="display:none">
                    @foreach($observations->slice(3) as $text)
                        <div class="su-quote">{{ $text }}</div>
                    @endforeach
                </div>
                <button type="button" class="su-more" data-reveal="more-observations">Show all {{ $observations->count() }} observations</button>
            @endif
        </div>
    </div>

    @if($canEncode && ! $evaluation->isSubmitted() && $responseCount > 0)
        <form method="POST" action="{{ route('evaluations.finish', $announcement) }}" style="margin-top:18px"
              data-confirm
              data-confirm-title="{{ $submissionWarning ? 'Submit incomplete batch?' : 'Submit this evaluation batch?' }}"
              data-confirm-body="Submit {{ $responsesByType->get('beneficiary', collect())->count() }} beneficiary forms{{ $evaluation->paper_beneficiary_count !== null ? ' of '.$evaluation->paper_beneficiary_count.' received' : '' }} and {{ $responsesByType->get('implementer', collect())->count() }} volunteer / implementer forms{{ $evaluation->paper_implementer_count !== null ? ' of '.$evaluation->paper_implementer_count.' received' : '' }}{{ $submissionWarning ? '. '.$submissionWarning : '. All recorded paper forms are accounted for.' }}"
              data-confirm-note="{{ $submissionWarning ? 'You can reopen the batch later if a correction is needed.' : 'All recorded paper forms are accounted for. You can reopen the batch later if a correction is needed.' }}"
              data-confirm-ok="Submit batch"
              data-confirm-cancel="Keep reviewing"
              data-confirm-tone="{{ $submissionWarning ? 'warning' : 'success' }}">
            @csrf
            @if($submissionWarning)
                <label style="display:flex; align-items:flex-start; gap:8px; margin-bottom:12px; color:#9a3412; font-size:.88rem">
                    <input type="checkbox" name="confirm_incomplete" value="1" required style="margin-top:3px">
                    <span>{{ $submissionWarning }} I understand that I am submitting this batch before all recorded paper forms are encoded.</span>
                </label>
            @endif
            <button type="submit"
                    style="padding:12px 20px; border-radius:12px; background:#0b6a4a; color:#fff; border:none; font-weight:700; cursor:pointer; font-size:0.92rem">
                Submit this batch
            </button>
            <span style="margin-left:12px; color:#64748b; font-size:0.87rem">You can reopen the batch later if a correction is needed.</span>
        </form>
    @endif

    @if($history->isNotEmpty())
        <section class="su-card"><h3>Submission and correction history</h3>
            @foreach($history as $event)
                <div style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#475569">
                    <strong>{{ ucfirst($event->action) }}</strong> by {{ $event->user->name ?? 'Unavailable user' }} · {{ $event->created_at->timezone(config('app.display_timezone', 'Asia/Manila'))->format('j M Y, g:i A') }}
                    · {{ $event->beneficiary_count }} beneficiary, {{ $event->implementer_count }} volunteer / implementer forms
                    @if($event->reason)<div>Reason: {{ $event->reason }}</div>@endif
                </div>
            @endforeach
        </section>
    @endif

    @if($evaluation->isSubmitted())
        <div class="su-note">
            <span aria-hidden="true">✅</span>
            <div>
                Batch submitted {{ $evaluation->submitted_at->format('j M Y, g:i A') }}
                @if($evaluation->submitter) by {{ $evaluation->submitter->name }} @endif.
                Averages are computed from the individual responses stored against this activity.
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.su-more').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var target = document.getElementById(btn.dataset.reveal);
                    if (!target) return;
                    target.style.display = 'block';
                    btn.style.display = 'none';
                });
            });
        });
    </script>
@endsection
