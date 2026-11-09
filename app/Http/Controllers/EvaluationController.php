<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationCustomAnswer;
use App\Models\EvaluationCustomQuestion;
use App\Models\EvaluationResponse;
use App\Models\EvaluationResponseRating;
use App\Support\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

/**
 * Beneficiary evaluations for completed activities.
 *
 * Beneficiaries answer a printed form at the venue. The project initiator —
 * the student organization or college/department that ran the activity —
 * encodes those forms here afterwards, one saved form per respondent. CELP
 * staff read the resulting summaries but never encode.
 */
class EvaluationController extends Controller
{
    /** Roles that run activities and therefore encode their own forms. */
    private const INITIATOR_ROLES = ['student_org', 'college_department'];

    /** Roles that read every summary but encode nothing. */
    private const OVERSIGHT_ROLES = ['celp_secretary', 'coordinator', 'celp_head', 'csa_director'];

    /**
     * Activities eligible for evaluation: the initiator's own completed
     * activities under a project that cleared review.
     */
    private function eligibleQuery(Request $request)
    {
        $user = $request->user();

        $query = Announcement::query()
            // Response counts are eager loaded with the batch; the list shows
            // one per row and would otherwise issue a query per activity.
            ->with([
                'creator',
                'project',
                'evaluation' => fn ($e) => $e->withCount([
                    'responses',
                    'responses as beneficiary_responses_count' => fn ($responses) => $responses->where('respondent_type', 'beneficiary'),
                    'responses as implementer_responses_count' => fn ($responses) => $responses->where('respondent_type', 'implementer'),
                ]),
            ])
            ->whereNotNull('event_date')
            // event_date is stored as a Manila-local datetime, not UTC.
            ->where('event_date', '<=', Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'))
            ->whereHas('project', fn ($p) => $p->whereIn('status', ['approved', 'csa_cleared']));

        // Initiators see only what they ran. Oversight roles see everything.
        if (in_array($user->role, self::INITIATOR_ROLES, true)) {
            $query->where('created_by', $user->id);
        } elseif (! in_array($user->role, self::OVERSIGHT_ROLES, true)) {
            abort(403, 'Unauthorized');
        }

        return $query;
    }

    public function index(Request $request)
    {
        $activities = $this->eligibleQuery($request)
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('title', 'like', "%{$term}%")
                        ->orWhere('event_location', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('event_date')
            ->get();

        // AY options come from the full eligible set, so the filter still
        // offers a year even after it has been filtered out of the list.
        $ayOptions = AcademicPeriod::labelsFrom(
            $this->eligibleQuery($request)->pluck('event_date')
        );

        $ay = $request->query('ay') ?: AcademicPeriod::current();
        if ($range = AcademicPeriod::range($ay)) {
            [$from, $to] = $range;
            $activities = $activities->filter(
                fn ($a) => $a->event_date >= $from && $a->event_date <= $to
            );
        }

        // Status is derived from the responses on file, so it is filtered
        // here rather than in SQL.
        $status = $request->query('status');
        if ($status && array_key_exists($status, Evaluation::STATUS_LABELS)) {
            $activities = $activities->filter(
                fn ($a) => $this->statusOf($a) === $status
            );
        }

        return view('evaluations.index', [
            'activities' => $activities->values(),
            'ayOptions' => $ayOptions,
            'ay' => $ay,
            'status' => $status,
            'q' => $request->query('q'),
            'canEncode' => in_array($request->user()->role, self::INITIATOR_ROLES, true),
        ]);
    }

    /** Status of an activity that may not have an evaluation row yet. */
    private function statusOf(Announcement $announcement): string
    {
        return $announcement->evaluation?->status() ?? 'not_started';
    }

    /**
     * Guard for anything that writes. Only the organization that ran the
     * activity may encode, and only while the batch is open.
     */
    private function authorizeEncoding(Request $request, Announcement $announcement): void
    {
        $user = $request->user();

        abort_unless(in_array($user->role, self::INITIATOR_ROLES, true), 403,
            'Only the project initiator encodes beneficiary evaluations.');

        abort_unless($announcement->created_by === $user->id, 403,
            'You may only encode evaluations for activities your organization ran.');

        abort_unless($announcement->project()
            ->whereIn('status', ['approved', 'csa_cleared'])
            ->exists(), 403,
            'This activity is not part of an approved project.');

        abort_unless($announcement->eventHasPassed(), 403,
            'This activity has not taken place yet.');
    }

    /** Guard for read-only screens. */
    private function authorizeViewing(Request $request, Announcement $announcement): void
    {
        $user = $request->user();

        if (in_array($user->role, self::OVERSIGHT_ROLES, true)) {
            return;
        }

        abort_unless(
            in_array($user->role, self::INITIATOR_ROLES, true)
                && $announcement->created_by === $user->id,
            403,
            'Unauthorized'
        );
    }

    /** The batch is created on first visit, not before it is needed. */
    private function batchFor(Announcement $announcement): Evaluation
    {
        return Evaluation::firstOrCreate(['announcement_id' => $announcement->id]);
    }

    public function encode(Request $request, Announcement $announcement)
    {
        $this->authorizeEncoding($request, $announcement);

        $evaluation = $this->batchFor($announcement);

        abort_if($evaluation->isSubmitted(), 403,
            'This evaluation batch has been submitted and can no longer be edited.');

        $respondentType = $request->query('respondent_type', 'beneficiary');
        abort_unless(in_array($respondentType, ['beneficiary', 'implementer'], true), 422, 'Invalid respondent type.');

        return view('STUDENT_ORGANIZATION_PRESIDENT.evaluations.encode', [
            'announcement' => $announcement->load('creator'),
            'evaluation' => $evaluation,
            'respondentType' => $respondentType,
            'editResponse' => $request->query('edit_response')
                ? $evaluation->responses()
                    ->where('respondent_type', $respondentType)
                    ->with(['ratings', 'customAnswers'])
                    ->findOrFail($request->query('edit_response'))
                : null,
            'savedResponses' => $evaluation->responses()
                ->where('respondent_type', $respondentType)
                ->orderBy('id')
                ->get(['id', 'evaluation_id', 'respondent_type', 'created_at']),
            'respondentTypes' => [
                'beneficiary' => 'Beneficiary',
                'implementer' => 'Student Volunteer / Implementer',
            ],
            'criteria' => EvaluationCriterion::active($respondentType),
            'heardAboutOptions' => EvaluationResponse::heardAboutOptions($respondentType),
            'customQuestions' => $evaluation->customQuestions()
                ->where('respondent_type', $respondentType)
                ->orderBy('sort_order')
                ->get(),
            'questionsLocked' => $evaluation->responses()->exists(),
            'responseCounts' => $evaluation->responses()
                ->selectRaw('respondent_type, COUNT(*) as total')
                ->groupBy('respondent_type')
                ->pluck('total', 'respondent_type'),
            'encodedCount' => $evaluation->responses()->where('respondent_type', $respondentType)->count(),
            'totalEncodedCount' => $evaluation->responses()->count(),
            'runningAverage' => $evaluation->overallAverage($respondentType),
            'criterionAverages' => $evaluation->averagesByCriterion($respondentType),
        ]);
    }

    /**
     * Save one encoded paper form. Each call creates a new response with its
     * own set of criterion scores; nothing is ever hand-tallied.
     */
    public function storeResponse(Request $request, Announcement $announcement)
    {
        return $this->saveResponse($request, $announcement);
    }

    public function updateResponse(Request $request, Announcement $announcement, EvaluationResponse $response)
    {
        return $this->saveResponse($request, $announcement, $response);
    }

    private function saveResponse(Request $request, Announcement $announcement, ?EvaluationResponse $response = null)
    {
        $this->authorizeEncoding($request, $announcement);

        $evaluation = $this->batchFor($announcement);

        abort_if($evaluation->isSubmitted(), 403,
            'This evaluation batch has been submitted and can no longer be edited.');

        $respondentType = $request->input('respondent_type');
        abort_unless(in_array($respondentType, ['beneficiary', 'implementer'], true), 422, 'Select a valid respondent group.');
        if ($response) {
            abort_unless(
                $response->evaluation_id === $evaluation->id
                    && $response->respondent_type === $respondentType,
                404
            );
        }
        $criteria = EvaluationCriterion::active($respondentType);
        $questions = $evaluation->customQuestions()
            ->where('respondent_type', $respondentType)
            ->orderBy('sort_order')
            ->get();

        $rules = [
            'heard_about' => ['nullable', 'in:' . implode(',', array_keys(EvaluationResponse::HEARD_ABOUT))],
            'heard_about_sources' => ['nullable', 'array'],
            'heard_about_sources.*' => [Rule::in(array_keys(EvaluationResponse::heardAboutOptions($respondentType)))],
            'suggestions' => ['nullable', 'string', 'max:1000'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'volunteer_interest' => ['nullable', 'in:yes,no,maybe'],
        ];
        $messages = [];

        // Every active criterion must carry a score — a partially rated form
        // would silently skew that criterion's average.
        foreach ($criteria as $criterion) {
            $rules["ratings.{$criterion->id}"] = ['required', 'integer', 'between:1,5'];
            $messages["ratings.{$criterion->id}.required"] =
                'Rate "' . $criterion->short_label . '" before saving this form.';
        }

        foreach ($questions as $question) {
            $field = "custom_answers.{$question->id}";
            $required = $question->is_required ? 'required' : 'nullable';
            $rules[$field] = match ($question->question_type) {
                'rating' => [$required, 'integer', 'between:1,5'],
                'text' => [$required, 'string', 'max:1000'],
                'yes_no_maybe' => [$required, 'in:yes,no,maybe'],
                'multiple_choice' => [$required, Rule::in($question->options ?? [])],
                default => ['prohibited'],
            };
            if ($question->is_required) {
                $messages["{$field}.required"] = 'Answer "' . $question->question . '" before saving this form.';
            }
        }

        $data = $request->validate($rules, $messages);

        DB::transaction(function () use ($data, $evaluation, $criteria, $questions, $respondentType, $request, &$response) {
            $attributes = [
                'heard_about' => $data['heard_about'] ?? null,
                'heard_about_sources' => $data['heard_about_sources'] ?? null,
                'volunteer_interest' => $data['volunteer_interest'] ?? null,
                'suggestions' => $data['suggestions'] ?? null,
                'observations' => $data['observations'] ?? null,
            ];
            if ($response) {
                $response->update($attributes);
            } else {
                $response = EvaluationResponse::create($attributes + [
                    'evaluation_id' => $evaluation->id,
                    'respondent_type' => $respondentType,
                    'source' => EvaluationResponse::SOURCE_PAPER,
                    'encoded_by' => $request->user()->id,
                ]);
            }

            foreach ($criteria as $criterion) {
                EvaluationResponseRating::updateOrCreate(
                    ['response_id' => $response->id, 'criterion_id' => $criterion->id],
                    ['score' => (int) $data['ratings'][$criterion->id]]
                );
            }

            foreach ($questions as $question) {
                $answer = $data['custom_answers'][$question->id] ?? null;
                if ($answer !== null && $answer !== '') {
                    EvaluationCustomAnswer::updateOrCreate(
                        ['response_id' => $response->id, 'question_id' => $question->id],
                        ['answer' => (string) $answer]
                    );
                } else {
                    EvaluationCustomAnswer::query()
                        ->where('response_id', $response->id)
                        ->where('question_id', $question->id)
                        ->delete();
                }
            }
        });

        $total = $evaluation->responses()->count();
        $wasEditing = $response->wasRecentlyCreated === false && $response->exists;

        // "Save & finish batch" closes the batch in the same action.
        if ($request->input('action') === 'finish') {
            $evaluation->update([
                'submitted_at' => now(),
                'submitted_by' => $request->user()->id,
            ]);

            return Redirect::route('evaluations.summary', $announcement)
                ->with('status', "Batch submitted with {$total} encoded forms.");
        }

        return Redirect::route('evaluations.encode', [
            'announcement' => $announcement,
            'respondent_type' => $respondentType,
        ])
            ->with('status', $wasEditing
                ? 'Saved form updated.'
                : "Form {$total} saved. Ready for the next one.");
    }

    public function storeQuestion(Request $request, Announcement $announcement)
    {
        $this->authorizeEncoding($request, $announcement);
        $evaluation = $this->batchFor($announcement);
        abort_if($evaluation->isSubmitted(), 403, 'This evaluation batch has been submitted and can no longer be edited.');
        abort_if($evaluation->responses()->exists(), 403, 'Questions are locked after the first response is saved.');

        $data = $request->validate([
            'respondent_type' => ['required', 'in:beneficiary,implementer'],
            'question' => ['required', 'string', 'max:255'],
            'question_type' => ['required', Rule::in(array_keys(EvaluationCustomQuestion::TYPES))],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $options = match ($data['question_type']) {
            'yes_no_maybe' => ['Yes', 'No', 'Maybe'],
            'multiple_choice' => array_values(array_unique(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $data['options'] ?? '')
            )))),
            default => null,
        };
        if ($data['question_type'] === 'multiple_choice' && count($options) < 2) {
            return Redirect::back()->withErrors([
                'options' => 'Enter at least two answer choices, one per line.',
            ])->withInput();
        }

        EvaluationCustomQuestion::create([
            'evaluation_id' => $evaluation->id,
            'respondent_type' => $data['respondent_type'],
            'question' => trim($data['question']),
            'question_type' => $data['question_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => (int) $evaluation->customQuestions()
                ->where('respondent_type', $data['respondent_type'])
                ->max('sort_order') + 1,
        ]);

        return Redirect::route('evaluations.encode', [
            'announcement' => $announcement,
            'respondent_type' => $data['respondent_type'],
        ])->with('status', 'Custom question added to the form.');
    }

    public function updateQuestion(Request $request, Announcement $announcement, EvaluationCustomQuestion $question)
    {
        $this->authorizeEncoding($request, $announcement);
        $evaluation = $this->batchFor($announcement);
        abort_unless($question->evaluation_id === $evaluation->id, 404);
        abort_if($evaluation->isSubmitted(), 403, 'This evaluation batch has been submitted and can no longer be edited.');
        abort_if($evaluation->responses()->exists(), 403, 'Questions are locked after the first response is saved.');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'question_type' => ['required', Rule::in(array_keys(EvaluationCustomQuestion::TYPES))],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $options = match ($data['question_type']) {
            'yes_no_maybe' => ['Yes', 'No', 'Maybe'],
            'multiple_choice' => array_values(array_unique(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $data['options'] ?? '')
            )))),
            default => null,
        };
        if ($data['question_type'] === 'multiple_choice' && count($options) < 2) {
            return Redirect::back()->withErrors([
                'options' => 'Enter at least two answer choices, one per line.',
            ])->withInput();
        }

        $question->update([
            'question' => trim($data['question']),
            'question_type' => $data['question_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
        ]);

        return Redirect::route('evaluations.encode', [
            'announcement' => $announcement,
            'respondent_type' => $question->respondent_type,
        ])->with('status', 'Additional question updated.');
    }

    public function destroyQuestion(Request $request, Announcement $announcement, EvaluationCustomQuestion $question)
    {
        $this->authorizeEncoding($request, $announcement);
        $evaluation = $this->batchFor($announcement);
        abort_unless($question->evaluation_id === $evaluation->id, 404);
        abort_if($evaluation->isSubmitted() || $evaluation->responses()->exists(), 403,
            'Questions are locked after the first response is saved.');

        $respondentType = $question->respondent_type;
        $question->delete();

        return Redirect::route('evaluations.encode', [
            'announcement' => $announcement,
            'respondent_type' => $respondentType,
        ])->with('status', 'Custom question removed.');
    }

    /** Record the beneficiary headcount, so progress reads "12 of 38". */
    public function updateTarget(Request $request, Announcement $announcement)
    {
        $this->authorizeEncoding($request, $announcement);
        $evaluation = $this->batchFor($announcement);
        abort_if($evaluation->isSubmitted(), 403,
            'This evaluation batch has been submitted and can no longer be edited.');

        $data = $request->validate([
            'expected_beneficiaries' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'expected_beneficiaries.required' => 'Enter how many beneficiaries attended.',
        ]);

        $evaluation->update($data);

        return Redirect::back()->with('status', 'Beneficiary count updated.');
    }

    /** Close the batch without encoding a further form. */
    public function finish(Request $request, Announcement $announcement)
    {
        $this->authorizeEncoding($request, $announcement);

        $evaluation = $this->batchFor($announcement);

        abort_if($evaluation->isSubmitted(), 403, 'This batch is already submitted.');

        if ($evaluation->responses()->count() === 0) {
            return Redirect::back()->withErrors([
                'batch' => 'Encode at least one form before submitting the batch.',
            ]);
        }

        $evaluation->update([
            'submitted_at' => now(),
            'submitted_by' => $request->user()->id,
        ]);

        return Redirect::route('evaluations.summary', $announcement)
            ->with('status', 'Evaluation batch submitted.');
    }

    public function summary(Request $request, Announcement $announcement)
    {
        $this->authorizeViewing($request, $announcement);

        $evaluation = $announcement->evaluation;

        abort_if($evaluation === null, 404, 'No evaluation has been started for this activity.');

        $responses = $evaluation->responses()
            ->with(['customAnswers.question'])
            ->orderBy('created_at')
            ->get();
        $responsesByType = $responses->groupBy('respondent_type');
        $averages = [
            'beneficiary' => $evaluation->averagesByCriterion('beneficiary'),
            'implementer' => $evaluation->averagesByCriterion('implementer'),
        ];
        $overallByType = [
            'beneficiary' => $evaluation->overallAverage('beneficiary'),
            'implementer' => $evaluation->overallAverage('implementer'),
        ];

        $criteria = EvaluationCriterion::query()
            ->where(function ($query) use ($averages) {
                $query->where('is_active', true)
                    ->orWhereIn('id', array_merge(
                        array_keys($averages['beneficiary']),
                        array_keys($averages['implementer'])
                    ));
            })
            ->orderBy('sort_order')
            ->get();
        $customQuestions = $evaluation->customQuestions()
            ->with(['answers.response'])
            ->orderBy('respondent_type')
            ->orderBy('sort_order')
            ->get();

        return view('evaluations.summary', [
            'announcement' => $announcement->load('creator'),
            'evaluation' => $evaluation,
            'criteria' => $criteria,
            'averages' => $averages,
            'overallByType' => $overallByType,
            'responsesByType' => $responsesByType,
            'customQuestions' => $customQuestions,
            'responseCount' => $responses->count(),
            'countsBySource' => $evaluation->countsBySource(),
            'sourceCounts' => $responses->pluck('heard_about_sources')->filter()
                ->flatten()->countBy()->all(),
            'heardAboutLabels' => EvaluationResponse::heardAboutLabels(),
            'suggestions' => $responses->pluck('suggestions')->filter()->values(),
            'observations' => $responses->pluck('observations')->filter()->values(),
            'volunteerInterestCounts' => $responses->groupBy('respondent_type')
                ->map(fn ($group) => $group->pluck('volunteer_interest')->filter()->countBy()->all())
                ->all(),
            'canEncode' => in_array($request->user()->role, self::INITIATOR_ROLES, true)
                && $announcement->created_by === $request->user()->id,
        ]);
    }
}
