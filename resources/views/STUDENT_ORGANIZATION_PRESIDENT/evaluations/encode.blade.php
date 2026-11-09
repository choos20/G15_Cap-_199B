@extends('layouts.admin')

@section('title', 'Encode '.$respondentTypes[$respondentType].' Evaluation')
@section('subtitle', $announcement->title)

@section('content')
    <style>
        .en-grid { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:20px; align-items:start; }
        @media (max-width: 980px) { .en-grid { grid-template-columns:1fr; } }

        .en-crumbs { font-size:0.85rem; color:#94a3b8; margin-bottom:14px; }
        .en-crumbs a { color:#0b6a4a; text-decoration:none; font-weight:600; }
        .en-crumbs a:hover { text-decoration:underline; }

        .en-badge { display:inline-flex; align-items:center; gap:7px; padding:7px 13px; border-radius:999px;
                    background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; font-size:0.82rem; font-weight:700; }

        .en-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:22px; }
        .en-card + .en-card { margin-top:18px; }
        .en-card-head { display:flex; align-items:baseline; gap:12px; flex-wrap:wrap; margin-bottom:4px; }
        .en-card-head h3 { margin:0; font-size:1.05rem; color:#0f172a; }
        .en-scale { font-size:0.82rem; color:#94a3b8; }
        .en-formno { margin-left:auto; font-size:0.82rem; color:#94a3b8; font-weight:600; }

        .en-crit { display:flex; align-items:center; gap:16px; padding:14px 0; border-bottom:1px solid #f1f5f9; }
        .en-crit:last-of-type { border-bottom:none; }
        .en-crit-label { flex:1; color:#334155; font-size:0.94rem; }
        .en-scores { display:flex; gap:7px; flex-shrink:0; }
        .en-score input { position:absolute; opacity:0; width:0; height:0; }
        .en-score span {
            display:flex; align-items:center; justify-content:center;
            width:38px; height:38px; border-radius:10px; cursor:pointer;
            border:1px solid #e2e8f0; background:#fff; color:#475569; font-weight:700; font-size:0.9rem;
            transition:background .12s, color .12s, border-color .12s;
        }
        .en-score { position:relative; display:inline-flex; }
        .en-score:hover span { border-color:#0b6a4a; }
        .en-score input:checked + span { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
        .en-score input:focus-visible + span { outline:2px solid #0b6a4a; outline-offset:2px; }

        .en-chiprow { display:flex; gap:9px; flex-wrap:wrap; margin-top:10px; }
        .en-chip { position:relative; display:inline-flex; }
        .en-chip input { position:absolute; opacity:0; width:0; height:0; }
        .en-chip span { padding:8px 15px; border-radius:999px; border:1px solid #e2e8f0; background:#fff;
                        color:#475569; font-size:0.86rem; font-weight:600; cursor:pointer; }
        .en-chip input:checked + span { background:#ecfdf5; border-color:#0b6a4a; color:#0b6a4a; }
        .en-chip input:focus-visible + span { outline:2px solid #0b6a4a; outline-offset:2px; }

        .en-label { display:block; font-weight:700; color:#0f172a; margin-bottom:6px; font-size:0.92rem; }
        .en-label span { font-weight:400; color:#94a3b8; }
        .en-text { width:100%; padding:12px 14px; border:1px solid #e2e8f0; border-radius:12px;
                   font-family:inherit; font-size:0.92rem; resize:vertical; min-height:92px; }

        .en-side { position:sticky; top:20px; display:flex; flex-direction:column; gap:14px; }
        .en-panel { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:20px; }
        .en-panel h4 { margin:0 0 12px; font-size:0.88rem; color:#0f172a; }
        .en-big { font-size:2.2rem; font-weight:800; color:#0f172a; line-height:1; }
        .en-sub { font-size:0.85rem; color:#64748b; margin-top:4px; }
        .en-bar { height:6px; border-radius:999px; background:#f1f5f9; overflow:hidden; margin-top:12px; }
        .en-bar i { display:block; height:100%; background:#0b6a4a; border-radius:999px; }
        .en-avgrow { display:flex; justify-content:space-between; gap:12px; padding:6px 0; font-size:0.85rem; color:#475569; }
        .en-avgrow b { color:#0f172a; font-variant-numeric:tabular-nums; }

        .en-btn { display:block; width:100%; padding:13px 16px; border-radius:12px; font-weight:700;
                  font-size:0.92rem; cursor:pointer; text-align:center; border:1px solid transparent; }
        .en-btn-primary { background:#0b6a4a; color:#fff; }
        .en-btn-primary:hover { background:#095a3e; }
        .en-btn-ghost { background:#fff; color:#334155; border-color:#e2e8f0; }
        .en-btn-ghost:hover { background:#f8fafc; }
        .en-btn-quiet { background:none; color:#94a3b8; border:none; font-size:0.86rem; padding:6px; }
        .en-btn-quiet:hover { color:#64748b; text-decoration:underline; }

        .en-target { display:flex; gap:8px; margin-top:12px; }
        .en-target input { flex:1; min-width:0; padding:9px 11px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.88rem; }
        .en-target button { padding:9px 13px; border-radius:10px; border:1px solid #e2e8f0; background:#f8fafc;
                            font-weight:700; font-size:0.84rem; cursor:pointer; color:#334155; }
        .en-config { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; align-items:end; }
        .en-config label { display:block; margin-bottom:5px; color:#334155; font-size:.82rem; font-weight:700; }
        .en-config input,.en-config select,.en-config textarea { width:100%; min-height:40px; padding:9px 11px; border:1px solid #e2e8f0; border-radius:9px; font:inherit; }
        .en-config textarea { min-height:78px; resize:vertical; }
        .en-question-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid #f1f5f9; color:#475569; font-size:.88rem; }
        .en-question-actions { display:flex; align-items:center; gap:12px; flex:0 0 auto; }
        .en-question-edit { display:grid; grid-template-columns:minmax(0,2fr) minmax(160px,1fr); gap:12px; padding:14px; margin:4px 0 12px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
        .en-question-edit[hidden] { display:none; }
        .en-question-edit label { display:block; margin-bottom:5px; color:#334155; font-size:.82rem; font-weight:700; }
        .en-question-edit input:not([type=checkbox]), .en-question-edit select, .en-question-edit textarea { width:100%; min-height:40px; padding:9px 11px; border:1px solid #e2e8f0; border-radius:9px; font:inherit; }
        .en-question-edit textarea { min-height:70px; resize:vertical; }
        .en-question-edit-actions { display:flex; align-items:center; gap:10px; grid-column:1 / -1; }
        .en-btn-inline { width:auto; display:inline-flex; align-items:center; justify-content:center; padding:7px 10px; font-size:.82rem; }
        @media (max-width:640px) { .en-question-item { align-items:flex-start; flex-direction:column; } .en-question-edit { grid-template-columns:1fr; } }
    </style>

    @php
        $target = $evaluation->expected_beneficiaries;
        $pct = $respondentType === 'beneficiary' && $target
            ? min(100, round($encodedCount / $target * 100))
            : 0;
        $editRatings = $editResponse?->ratings->keyBy('criterion_id') ?? collect();
        $editAnswers = $editResponse?->customAnswers->keyBy('question_id') ?? collect();
    @endphp

    <div class="en-crumbs">
        <a href="{{ route('student_org.evaluations') }}">Evaluations</a>
        &rsaquo; {{ $announcement->title }}
        &rsaquo; Encode response
    </div>

    @if(session('status'))
        <div style="margin-bottom:16px; padding:14px 18px; border-radius:12px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-weight:600">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px; padding:14px 18px; border-radius:12px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b">
            <div style="font-weight:700; margin-bottom:6px">This form could not be saved</div>
            <ul style="margin:0; padding-left:18px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="en-card" style="margin-bottom:18px">
        <form method="GET" action="{{ route('evaluations.encode', $announcement) }}" style="display:flex; align-items:end; gap:12px; flex-wrap:wrap">
            <div style="min-width:min(100%,300px)">
                <label class="en-label" for="respondent_type">Paper form type</label>
                <select id="respondent_type" name="respondent_type" class="en-text" style="min-height:44px" onchange="this.form.submit()">
                    @foreach($respondentTypes as $type => $label)
                        <option value="{{ $type }}" @selected($respondentType === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="color:#64748b; font-size:.85rem">
                Beneficiary forms and Student Volunteer / Implementer forms use separate rating questions and summaries.
            </div>
        </form>
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:12px; color:#64748b; font-size:.84rem">
            <span>Beneficiary forms: <b style="color:#0f172a">{{ $responseCounts['beneficiary'] ?? 0 }}</b></span>
            <span>Volunteer / implementer forms: <b style="color:#0f172a">{{ $responseCounts['implementer'] ?? 0 }}</b></span>
        </div>
    </div>

    <div class="en-card" style="margin-bottom:18px">
        <div class="en-card-head">
            <h3>Additional questions</h3>
            @if($questionsLocked)
                <span class="en-scale">Locked because responses have been saved.</span>
            @else
                <span class="en-scale">You can add questions until the first response is saved.</span>
            @endif
        </div>
        @forelse($customQuestions as $question)
            <div class="en-question-item">
                <span>
                    <b>{{ $question->question }}</b> · {{ \App\Models\EvaluationCustomQuestion::TYPES[$question->question_type] }}
                    @if($question->is_required) <span style="color:#b91c1c">Required</span> @endif
                    @if($question->question_type === 'multiple_choice' && $question->options)
                        <small style="display:block; color:#94a3b8; margin-top:3px">{{ implode(' · ', $question->options) }}</small>
                    @endif
                </span>
                @unless($questionsLocked)
                    <div class="en-question-actions">
                        <button type="button" class="en-btn en-btn-ghost en-btn-inline" aria-expanded="false"
                                aria-controls="edit-question-{{ $question->id }}"
                                onclick="toggleQuestionEditor({{ $question->id }}, this)">Edit</button>
                        <form method="POST" action="{{ route('evaluations.questions.destroy', [$announcement, $question]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="en-btn en-btn-quiet" data-confirm
                                    data-confirm-title="Remove additional question?"
                                    data-confirm-body="This question will be removed from the evaluation form."
                                    data-confirm-tone="warning"
                                    data-confirm-ok="Remove question"
                                    data-confirm-cancel="Keep question">Remove</button>
                        </form>
                    </div>
                @endunless
            </div>
            @unless($questionsLocked)
                <form id="edit-question-{{ $question->id }}" class="en-question-edit" method="POST"
                      action="{{ route('evaluations.questions.update', [$announcement, $question]) }}" hidden>
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="edit-question-text-{{ $question->id }}">Question</label>
                        <input id="edit-question-text-{{ $question->id }}" name="question" value="{{ $question->question }}" maxlength="255" required>
                    </div>
                    <div>
                        <label for="edit-question-type-{{ $question->id }}">Answer type</label>
                        <select id="edit-question-type-{{ $question->id }}" name="question_type" required>
                            @foreach(\App\Models\EvaluationCustomQuestion::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected($question->question_type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="edit-question-options" style="grid-column:1 / -1" @if($question->question_type !== 'multiple_choice') hidden @endif>
                        <label for="edit-question-options-{{ $question->id }}">Answer choices (one per line)</label>
                        <textarea id="edit-question-options-{{ $question->id }}" name="options" placeholder="First choice&#10;Second choice">{{ implode("\n", $question->options ?? []) }}</textarea>
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; margin:0">
                        <input type="checkbox" name="is_required" value="1" @checked($question->is_required) style="width:auto; min-height:0">
                        Require an answer
                    </label>
                    <div class="en-question-edit-actions">
                        <button type="submit" class="en-btn en-btn-primary en-btn-inline">Save changes</button>
                        <button type="button" class="en-btn en-btn-quiet" onclick="toggleQuestionEditor({{ $question->id }}, document.querySelector('[aria-controls=&quot;edit-question-{{ $question->id }}&quot;]'))">Cancel</button>
                    </div>
                </form>
            @endunless
        @empty
            <p style="color:#94a3b8; font-size:.87rem">No additional questions for this respondent group.</p>
        @endforelse
        @unless($questionsLocked)
            <form method="POST" action="{{ route('evaluations.questions.store', $announcement) }}" style="margin-top:16px">
                @csrf
                <input type="hidden" name="respondent_type" value="{{ $respondentType }}">
                <div class="en-config">
                    <div style="grid-column:span 2">
                        <label for="custom-question">Question</label>
                        <input id="custom-question" name="question" value="{{ old('question') }}" maxlength="255" required placeholder="Write an additional question">
                    </div>
                    <div>
                        <label for="question-type">Answer type</label>
                        <select id="question-type" name="question_type" required>
                            @foreach(\App\Models\EvaluationCustomQuestion::TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('question_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="question-options" style="grid-column:span 2">
                        <label for="question-options-input">Answer choices (multiple choice only, one per line)</label>
                        <textarea id="question-options-input" name="options" placeholder="First choice&#10;Second choice">{{ old('options') }}</textarea>
                    </div>
                    <label style="display:flex; align-items:center; gap:8px; margin:0">
                        <input type="checkbox" name="is_required" value="1" @checked(old('is_required')) style="width:auto; min-height:0">
                        Require an answer
                    </label>
                    <button type="submit" class="en-btn en-btn-primary">Add question</button>
                </div>
            </form>
        @endunless
    </div>

    @if($savedResponses->isNotEmpty())
        <div class="en-card" style="margin-bottom:18px">
            <div class="en-card-head">
                <h3>Saved {{ $respondentTypes[$respondentType] }} forms</h3>
                <span class="en-scale">Edit a form to correct an answer before submitting the batch.</span>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px">
                @foreach($savedResponses as $savedResponse)
                    <a href="{{ route('evaluations.encode', [$announcement, 'respondent_type' => $respondentType, 'edit_response' => $savedResponse->id]) }}"
                       class="en-btn en-btn-ghost en-btn-inline"
                       @if($editResponse?->id === $savedResponse->id) aria-current="page" style="border-color:#0b6a4a; color:#0b6a4a" @endif>
                        Form {{ $loop->iteration }}{{ $editResponse?->id === $savedResponse->id ? ' · Editing' : ' · Edit' }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST"
          action="{{ $editResponse
              ? route('evaluations.responses.update', [$announcement, $editResponse])
              : route('evaluations.responses.store', $announcement) }}">
        @csrf
        @if($editResponse) @method('PUT') @endif
        <input type="hidden" name="respondent_type" value="{{ $respondentType }}">
        <div class="en-grid">
            <div>
                <div class="en-card">
                    <div style="display:flex; align-items:flex-start; gap:12px; flex-wrap:wrap">
                        <div>
                            <div style="font-weight:800; color:#0f172a; font-size:1.05rem">{{ $announcement->title }}</div>
                            <div style="font-size:0.87rem; color:#64748b; margin-top:4px">
                                {{ $announcement->event_location ?? 'Location not recorded' }}
                                &middot; {{ $announcement->event_date->format('j F Y') }}
                                @if($announcement->creator)
                                    &middot; {{ $announcement->creator->organization_name ?? $announcement->creator->name }}
                                @endif
                            </div>
                        </div>
                        <span class="en-badge" style="margin-left:auto; background:#ecfdf5; border-color:#a7f3d0; color:#065f46">Completed</span>
                    </div>
                </div>

                <div class="en-card">
                    <div class="en-card-head">
                        <h3>{{ $respondentTypes[$respondentType] }} rating criteria</h3>
                        <span class="en-formno">
                            Form {{ $editResponse ? $savedResponses->search(fn ($saved) => $saved->id === $editResponse->id) + 1 : $encodedCount + 1 }}
                            @if($respondentType === 'beneficiary' && $target) of {{ $target }}@endif
                            @if($editResponse) · Editing saved form @endif
                        </span>
                    </div>
                    <div class="en-scale">
                        @foreach(\App\Models\EvaluationCriterion::SCALE as $n => $word)
                            {{ $n }} &ndash; {{ $word }}@if(! $loop->last) &nbsp;&middot;&nbsp; @endif
                        @endforeach
                    </div>

                    <div style="margin-top:10px">
                        @foreach($criteria as $criterion)
                            <div class="en-crit">
                                <div class="en-crit-label">{{ $criterion->label }}</div>
                                <div class="en-scores">
                                    @for($n = 1; $n <= 5; $n++)
                                        <label class="en-score" title="{{ \App\Models\EvaluationCriterion::SCALE[$n] }}">
                                            <input type="radio" name="ratings[{{ $criterion->id }}]" value="{{ $n }}"
                                                   @checked((int) old("ratings.{$criterion->id}", $editRatings[$criterion->id]->score ?? null) === $n)>
                                            <span>{{ $n }}</span>
                                        </label>
                                    @endfor
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @foreach($customQuestions as $question)
                    <div class="en-card">
                        <label class="en-label" for="custom-answer-{{ $question->id }}">
                            {{ $question->question }}
                            @if($question->is_required)<span style="color:#b91c1c">Required</span>@else<span>&mdash; optional</span>@endif
                        </label>
                        @switch($question->question_type)
                            @case('rating')
                                <div class="en-scores">
                                    @for($n = 1; $n <= 5; $n++)
                                        <label class="en-score">
                                            <input type="radio" name="custom_answers[{{ $question->id }}]" value="{{ $n }}" @checked((string) old("custom_answers.{$question->id}", $editAnswers[$question->id]->answer ?? '') === (string) $n)>
                                            <span>{{ $n }}</span>
                                        </label>
                                    @endfor
                                </div>
                                @break
                            @case('yes_no_maybe')
                                <div class="en-chiprow">
                                    @foreach(['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                                        <label class="en-chip"><input type="radio" name="custom_answers[{{ $question->id }}]" value="{{ $value }}" @checked(old("custom_answers.{$question->id}", $editAnswers[$question->id]->answer ?? '') === $value)><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                                @break
                            @case('multiple_choice')
                                <select id="custom-answer-{{ $question->id }}" name="custom_answers[{{ $question->id }}]" class="en-text" style="min-height:44px">
                                    <option value="">Select an answer</option>
                                    @foreach($question->options ?? [] as $option)
                                        <option value="{{ $option }}" @selected(old("custom_answers.{$question->id}", $editAnswers[$question->id]->answer ?? '') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @break
                            @default
                                <textarea id="custom-answer-{{ $question->id }}" name="custom_answers[{{ $question->id }}]" class="en-text" maxlength="1000">{{ old("custom_answers.{$question->id}", $editAnswers[$question->id]->answer ?? '') }}</textarea>
                        @endswitch
                    </div>
                @endforeach

                <div class="en-card">
                    <label class="en-label">How did the respondent hear about the activity? <span>&mdash; select all that apply</span></label>
                    <div class="en-chiprow">
                        @foreach($heardAboutOptions as $key => $label)
                            <label class="en-chip">
                                <input type="checkbox" name="heard_about_sources[]" value="{{ $key }}" @checked(in_array($key, old('heard_about_sources', $editResponse?->heard_about_sources ?? []), true))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <label class="en-label" style="margin-top:18px">Would the respondent be interested in volunteering in similar activities?</label>
                    <div class="en-chiprow">
                        @foreach(['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                            <label class="en-chip"><input type="radio" name="volunteer_interest" value="{{ $value }}" @checked(old('volunteer_interest', $editResponse?->volunteer_interest) === $value)><span>{{ $label }}</span></label>
                        @endforeach
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:20px">
                        <div>
                            <label class="en-label" for="suggestions">Suggestions <span>&mdash; optional</span></label>
                            <textarea id="suggestions" name="suggestions" class="en-text"
                                      placeholder="Copy the respondent's suggestion as written...">{{ old('suggestions', $editResponse?->suggestions) }}</textarea>
                        </div>
                        <div>
                            <label class="en-label" for="observations">Observations <span>&mdash; optional</span></label>
                            <textarea id="observations" name="observations" class="en-text"
                                      placeholder="Notes about turnout, venue, or logistics...">{{ old('observations', $editResponse?->observations) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="en-side">
                <div class="en-panel">
                    <h4>This encoding batch</h4>
                    <div class="en-big">{{ $encodedCount }}</div>
                    <div class="en-sub">
                        @if($respondentType === 'beneficiary' && $target) of {{ $target }} forms encoded @else forms encoded so far @endif
                    </div>
                    @if($respondentType === 'beneficiary' && $target)
                        <div class="en-bar"><i style="width:{{ $pct }}%"></i></div>
                    @endif
                </div>

                @if($runningAverage !== null)
                    <div class="en-panel">
                        <h4>Running average</h4>
                        <div class="en-big">{{ number_format($runningAverage, 2) }}</div>
                        <div class="en-sub">out of 5.00</div>
                        <div style="margin-top:14px; border-top:1px solid #f1f5f9; padding-top:8px">
                            @foreach($criteria as $criterion)
                                <div class="en-avgrow">
                                    <span>{{ $criterion->short_label }}</span>
                                    <b>{{ isset($criterionAverages[$criterion->id]) ? number_format($criterionAverages[$criterion->id], 2) : '—' }}</b>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="en-panel" style="display:flex; flex-direction:column; gap:10px">
                    <button type="submit" name="action" value="another" class="en-btn en-btn-primary">
                        {{ $editResponse ? 'Update response' : '+ Save & add another' }}
                    </button>
                    <button type="submit" name="action" value="finish" class="en-btn en-btn-ghost">
                        {{ $editResponse ? 'Update & finish batch' : 'Save & finish batch' }}
                    </button>
                    <a href="{{ route('evaluations.encode', [$announcement, 'respondent_type' => $respondentType]) }}"
                       class="en-btn en-btn-quiet" style="text-decoration:none">
                        {{ $editResponse ? 'Cancel editing' : 'Discard this form' }}
                    </a>
                </div>

                @if($respondentType === 'beneficiary')
                    <div class="en-panel">
                        <h4>Beneficiaries who attended</h4>
                        <div class="en-sub" style="margin-top:0">Sets the target this batch is counted against.</div>
                        <div class="en-target">
                            <input type="number" form="target-form" name="expected_beneficiaries"
                                   min="1" value="{{ $target }}" placeholder="e.g. 38"
                                   aria-label="Number of beneficiaries who attended">
                            <button type="submit" form="target-form">Save</button>
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </form>

    <form id="target-form" method="POST" action="{{ route('evaluations.target', $announcement) }}">
        @csrf
    </form>

    <script>
        function toggleQuestionEditor(id, trigger) {
            const form = document.getElementById('edit-question-' + id);
            if (!form) return;
            form.hidden = !form.hidden;
            if (trigger) trigger.setAttribute('aria-expanded', String(!form.hidden));
        }

        document.addEventListener('DOMContentLoaded', function () {
            const bindOptions = (type, options) => {
                if (!type || !options) return;
                const update = () => { options.hidden = type.value !== 'multiple_choice'; };
                type.addEventListener('change', update);
                update();
            };
            bindOptions(document.getElementById('question-type'), document.getElementById('question-options'));
            document.querySelectorAll('[id^="edit-question-type-"]').forEach((type) => {
                bindOptions(type, type.closest('form').querySelector('.edit-question-options'));
            });
        });
    </script>

@endsection
