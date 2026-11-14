<?php

use App\Http\Controllers\ActivityDirectoryController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CelpCoordinatorController;
use App\Http\Controllers\CelpHeadController;
use App\Http\Controllers\CelpSecretaryController;
use App\Http\Controllers\CelpStaffReportController;
use App\Http\Controllers\CsaDirectorController;
use App\Http\Controllers\CsaReportController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationReportController;
use App\Http\Controllers\OrganizationDirectoryController;
use App\Http\Controllers\OrgProfileController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StudentOrgController;
use App\Http\Controllers\TemplateController;
use App\Mail\AccountCreated;
use App\Mail\EmailVerificationOtp;
use App\Mail\PasswordResetOtp;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Whether $auth is allowed to activate/deactivate $target's account.
 * CELP Head is the system admin, so it can suspend any account regardless of
 * role — except its own, which would lock it out of the admin area with no way
 * back in. CSA Director stays scoped to the org accounts it oversees.
 */
function can_manage_account_status(User $auth, User $target): bool
{
    if ($auth->id === $target->id) {
        return false;
    }

    // The CELP Head is the system administrator: it may suspend any account,
    // including the student organizations only the CSA Director may create.
    // Creating an account and being able to suspend one are separate rights.
    if ($auth->role === 'celp_head') {
        return true;
    }

    return in_array($target->role, manageable_roles_for($auth), true);
}

/**
 * Which roles each staff role may create.
 *
 * Per the CELP Head interview: CELP personnel (Head, Coordinator, Secretary)
 * handle the participant and College/Department accounts, while Student
 * Organization accounts are the CSA Director's alone. Only the CELP Head
 * creates other CELP staff.
 *
 * @return array<string, string> role slug => label shown on the create form
 */
function creatable_roles_for(?User $auth): array
{
    $participantAndCollege = [
        'participant' => 'Student, Employee or Alumni',
        'college_department' => 'College / Department',
    ];

    return match ($auth?->role) {
        'celp_head' => $participantAndCollege + [
            'celp_secretary' => 'CELP Secretary',
            'coordinator' => 'Coordinator',
            'csa_director' => 'CSA Director',
        ],
        'coordinator', 'celp_secretary' => $participantAndCollege,
        'csa_director' => ['student_org' => 'Student Organization'],
        default => [],
    };
}

/**
 * Which roles each staff role may edit, deactivate and reactivate. Mirrors
 * creatable_roles_for so nobody can create an account they then can't manage.
 *
 * @return array<int, string>
 */
function manageable_roles_for(?User $auth): array
{
    return array_keys(creatable_roles_for($auth));
}

/**
 * Whether $auth may open/save $target's profile. Kept beside the status rule so
 * the blade table and the routes ask the same question — when these two drifted
 * apart the UI rendered Edit links that 403'd on click.
 */
function can_edit_account(User $auth, User $target): bool
{
    if ($auth->id === $target->id || $auth->role === 'celp_head') {
        return true;
    }

    // The CSA Director edits only the organizations it registered; CELP staff
    // may edit any account in the roles they are responsible for.
    if ($auth->role === 'csa_director') {
        return $target->role === 'student_org' && $target->created_by === $auth->id;
    }

    return in_array($target->role, manageable_roles_for($auth), true);
}

/**
 * Account-name and email changes are limited to the CELP Head and the CSA
 * Director managing student-organization accounts they created.
 */
function can_change_account_identity(User $auth, User $target): bool
{
    if ($auth->role === 'celp_head') {
        return true;
    }

    return $auth->role === 'csa_director'
        && $target->role === 'student_org'
        && $target->created_by === $auth->id;
}

const OTP_TTL_MINUTES = 3;
const OTP_MAX_ATTEMPTS = 3;

/**
 * The dashboard a user belongs on.
 *
 * Every role has its own dashboard, but the mapping used to live only inside
 * the login route's if-chain — so every other redirect fell back to the generic
 * /dashboard page, which renders on the public login layout. One helper keeps
 * login and every post-action redirect agreeing.
 */
function dashboard_route_for(?User $user): string
{
    return match ($user?->role) {
        'celp_head' => 'celp.head.dashboard',
        'celp_secretary' => 'celp.secretary.dashboard',
        'coordinator' => 'celp.coordinator.dashboard',
        'csa_director' => 'csa.director.dashboard',
        'student_org', 'college_department' => 'student_org.dashboard',
        'participant' => 'participant.dashboard',
        default => 'dashboard',
    };
}

/**
 * Domains an account created by CELP Head or CSA Director may use.
 *
 * Open by default: not everyone being registered has an institutional mailbox,
 * and rejecting a personal address would leave those people unable to be
 * enrolled at all. Typos are caught instead by the retyped confirmation field
 * and by `email:rfc,dns`, which rejects a domain that cannot receive mail.
 *
 * Restrict again in .env if a policy ever requires it:
 *     ACCOUNT_EMAIL_DOMAINS=hnu.edu.ph            (one domain)
 *     ACCOUNT_EMAIL_DOMAINS=hnu.edu.ph,gmail.com  (several)
 * Empty or `*` allows any domain, which is the default.
 */
function allowed_account_email_domains(): array
{
    $raw = trim((string) env('ACCOUNT_EMAIL_DOMAINS', ''));

    if ($raw === '' || $raw === '*') {
        return [];
    }

    return array_values(array_filter(array_map(
        fn ($d) => strtolower(trim($d)),
        explode(',', $raw)
    )));
}

/**
 * Human-readable name for a role slug. Shared by the sidebar and any view that
 * needs to name a role, so "Administrator" is never hardcoded under a user who
 * isn't one.
 */
function role_label(?string $role): string
{
    return [
        'celp_head' => 'System Administrator',
        'coordinator' => 'Coordinator',
        'celp_secretary' => 'CELP Secretary',
        'csa_director' => 'CSA Director',
        'student_org' => 'Student Organization President',
        'college_department' => 'College / Department',
        'participant' => 'Participant',
    ][$role] ?? ucwords(str_replace('_', ' ', (string) $role));
}

/**
 * Seconds remaining before an OTP expires, clamped at 0. Session values are
 * unserialized as Carbon, but a string is accepted too in case an old-format
 * session survives a deploy.
 */
function otp_seconds_left($expiresAt): int
{
    if (! $expiresAt) {
        return 0;
    }

    return max(0, (int) now()->diffInSeconds(Carbon::parse($expiresAt), false));
}

/**
 * Generate a fresh 6-digit OTP for the given user, stash it in the session,
 * and email it. Shared by registration, login-gating, and the resend action
 * so all three stay in sync. Returns false (without throwing) if the email
 * couldn't be sent, so a transient SMTP hiccup never 500s a login attempt.
 */
function issue_email_verification_otp(User $user): bool
{
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    session([
        'verify_user_id' => $user->id,
        'verify_email' => $user->email,
        'verify_otp' => $otp,
        'verify_otp_expires_at' => now()->addMinutes(OTP_TTL_MINUTES),
        'verify_otp_attempts' => 0,
    ]);

    try {
        Mail::to($user->email)->send(new EmailVerificationOtp($otp, $user->name));

        return true;
    } catch (Throwable $e) {
        report($e);

        return false;
    }
}

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', function (Request $request) {
    $request->validate([
        'user' => 'required|string',
        'password' => 'required|string',
    ]);

    $credentials = filter_var($request->user, FILTER_VALIDATE_EMAIL)
        ? ['email' => $request->user, 'password' => $request->password]
        : ['username' => $request->user, 'password' => $request->password];

    if (! Auth::attempt($credentials)) {
        return back()->withErrors(['login' => 'Invalid username/email or password.'])->withInput();
    }

    $request->session()->regenerate();

    $user = Auth::user();
    if ($user && array_key_exists('is_active', $user->getAttributes()) && ! $user->is_active) {
        Auth::logout();

        return back()->withErrors(['login' => 'Your account is deactivated.']);
    }

    if ($user && ! $user->email_verified_at) {
        Auth::logout();
        $sent = issue_email_verification_otp($user);
        $status = $sent
            ? "Your email isn't verified yet. We sent a new code to {$user->email} — enter it below to continue."
            : "Your email isn't verified yet, and we couldn't send a code right now. Use \"Resend code\" below in a moment.";

        return redirect()->route('verify_email')->with('status', $status);
    }

    return redirect()->route(dashboard_route_for($user));
})->middleware('throttle:login')->name('login.submit');

Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

/**
 * Generic dashboard. Kept as a permanent alias so old bookmarks and any stray
 * redirect land on the caller's real dashboard instead of a bare fallback page.
 */
Route::get('/dashboard', function (Request $request) {
    $target = dashboard_route_for($request->user());

    // Guard against redirecting to ourselves if a role has no dashboard.
    if ($target === 'dashboard') {
        return view('dashboard');
    }

    return redirect()->route($target);
})->middleware('auth')->name('dashboard');

Route::get('/celp-head', [CelpHeadController::class, 'index'])
    ->middleware('auth')
    ->name('celp.head.dashboard');

Route::get('/celp-secretary', [CelpSecretaryController::class, 'index'])
    ->middleware('auth')
    ->name('celp.secretary.dashboard');

Route::get('/celp-coordinator', [CelpCoordinatorController::class, 'index'])
    ->middleware('auth')
    ->name('celp.coordinator.dashboard');

Route::get('/csa-director', [CsaDirectorController::class, 'index'])
    ->middleware('auth')
    ->name('csa.director.dashboard');

Route::get('/student-org', [StudentOrgController::class, 'index'])
    ->middleware('auth')
    ->name('student_org.dashboard');

Route::get('/student-org/proposals', [StudentOrgController::class, 'proposals'])
    ->middleware('auth')
    ->name('student_org.proposals');

Route::delete('/student-org/projects/{project}', [ProjectController::class, 'destroyOwnProposal'])
    ->middleware('auth')
    ->name('student_org.projects.destroy');

Route::get('/student-org/announcements', [AnnouncementController::class, 'index'])
    ->middleware('auth')
    ->name('student_org.announcements');

Route::post('/student-org/announcements', [AnnouncementController::class, 'store'])
    ->middleware('auth')
    ->name('student_org.announcements.store');

Route::get('/student-org/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])
    ->middleware('auth')
    ->name('student_org.announcements.edit');

Route::put('/student-org/announcements/{announcement}', [AnnouncementController::class, 'update'])
    ->middleware('auth')
    ->name('student_org.announcements.update');

Route::patch('/student-org/announcements/{announcement}/status', [AnnouncementController::class, 'updateStatus'])
    ->middleware('auth')
    ->name('student_org.announcements.status');

Route::get('/student-org/announcements/{announcement}', [AnnouncementController::class, 'show'])
    ->middleware('auth')
    ->name('student_org.announcements.show');

Route::delete('/student-org/announcements/{announcement}/attachments/{attachment}', [AnnouncementController::class, 'destroyAttachment'])
    ->middleware('auth')
    ->name('student_org.announcements.attachments.destroy');

Route::delete('/student-org/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
    ->middleware('auth')
    ->name('student_org.announcements.destroy');

Route::get('/celp-secretary/announcements', [AnnouncementController::class, 'secretaryIndex'])
    ->middleware('auth')
    ->name('celp.secretary.announcements');

Route::post('/celp-secretary/announcements', [AnnouncementController::class, 'secretaryStore'])
    ->middleware('auth')
    ->name('celp.secretary.announcements.store');

Route::get('/celp-secretary/announcements/{announcement}', [AnnouncementController::class, 'secretaryShow'])
    ->middleware('auth')
    ->name('celp.secretary.announcements.show');

Route::delete('/celp-secretary/announcements/{announcement}', [AnnouncementController::class, 'secretaryDestroy'])
    ->middleware('auth')
    ->name('celp.secretary.announcements.destroy');

Route::get('/student-org/volunteers', [StudentOrgController::class, 'volunteers'])
    ->middleware('auth')
    ->name('student_org.volunteers');

Route::get('/student-org/volunteers/{announcement}', [StudentOrgController::class, 'showVolunteerApplications'])
    ->middleware('auth')
    ->name('student_org.volunteers.show');

Route::post('/student-org/volunteers/applications/{application}/approve', [StudentOrgController::class, 'approveVolunteer'])
    ->middleware('auth')
    ->name('student_org.volunteers.approve');

Route::post('/student-org/volunteers/applications/{application}/decline', [StudentOrgController::class, 'declineVolunteer'])
    ->middleware('auth')
    ->name('student_org.volunteers.decline');

Route::get('/participant', [ParticipantController::class, 'dashboard'])
    ->middleware('auth')
    ->name('participant.dashboard');

Route::get('/participant/activities', [ParticipantController::class, 'feed'])
    ->middleware('auth')
    ->name('participant.activities');

Route::get('/participant/activities/{announcement}', [ParticipantController::class, 'show'])
    ->middleware('auth')
    ->name('participant.activities.show');

Route::post('/participant/activities/{announcement}/apply', [ParticipantController::class, 'apply'])
    ->middleware('auth')
    ->name('participant.activities.apply');

Route::get('/participant/applications', [ParticipantController::class, 'myApplications'])
    ->middleware('auth')
    ->name('participant.applications');

Route::get('/participant/attendance', [ParticipantController::class, 'attendanceHistory'])
    ->middleware('auth')
    ->name('participant.attendance');

Route::post('/participant/applications/{application}/withdraw', [ParticipantController::class, 'withdraw'])
    ->middleware('auth')
    ->name('participant.applications.withdraw');

Route::get('/participant/scanner', [ParticipantController::class, 'scanner'])
    ->middleware('auth')
    ->name('participant.scanner');

Route::get('/participant/scanner/resolve', [ParticipantController::class, 'resolveScan'])
    ->middleware('auth')
    ->name('participant.scanner.resolve');

Route::get('/volunteer-applications/documents/{attachment}', [ParticipantController::class, 'downloadDocument'])
    ->middleware('auth')
    ->name('volunteer_applications.documents.download');

Route::get('/student-org/attendance', [AttendanceController::class, 'index'])
    ->middleware('auth')
    ->name('student_org.attendance');

Route::post('/student-org/attendance', [AttendanceController::class, 'store'])
    ->middleware('auth')
    ->name('student_org.attendance.store');

Route::post('/student-org/attendance/{attendanceSession}/regenerate', [AttendanceController::class, 'regenerate'])
    ->middleware('auth')
    ->name('student_org.attendance.regenerate');

Route::post('/student-org/attendance/{attendanceSession}/close', [AttendanceController::class, 'close'])
    ->middleware('auth')
    ->name('student_org.attendance.close');

Route::get('/student-org/attendance/{attendanceSession}/records', [AttendanceController::class, 'records'])
    ->middleware('auth')
    ->name('student_org.attendance.records');

Route::get('/student-org/attendance/{attendanceSession}/csv-template', [AttendanceController::class, 'downloadAttendanceCsv'])
    ->middleware('auth')
    ->name('student_org.attendance.csv-template');

Route::get('/student-org/attendance/{attendanceSession}/signature-sheet', [AttendanceController::class, 'downloadSignatureSheet'])
    ->middleware('auth')
    ->name('student_org.attendance.signature-sheet');

Route::get('/student-org/attendance/{attendanceSession}/import', [AttendanceController::class, 'attendanceImport'])
    ->middleware('auth')
    ->name('student_org.attendance.import');

Route::post('/student-org/attendance/{attendanceSession}/import/preview', [AttendanceController::class, 'previewAttendanceImport'])
    ->middleware('auth')
    ->name('student_org.attendance.preview');

Route::post('/student-org/attendance/{attendanceSession}/import/confirm', [AttendanceController::class, 'confirmAttendanceImport'])
    ->middleware('auth')
    ->name('student_org.attendance.confirm');

Route::get('/attendance/scan/{token}', [AttendanceController::class, 'scanView'])
    ->middleware('auth')
    ->name('attendance.scan.view');

Route::post('/attendance/scan/{token}', [AttendanceController::class, 'scan'])
    ->middleware('auth')
    ->name('attendance.scan.submit');

/*
 | Beneficiary evaluations. The list keeps the student_org.evaluations name
 | because the sidebar already points at it; access inside the controller is
 | wider than the name suggests — initiators encode their own activities,
 | CELP and CSA staff read every summary.
 */
Route::middleware('auth')->group(function () {
    Route::get('/evaluations', [EvaluationController::class, 'index'])
        ->name('student_org.evaluations');

    Route::get('/evaluations/{announcement}/encode', [EvaluationController::class, 'encode'])
        ->name('evaluations.encode');

    Route::post('/evaluations/{announcement}/responses', [EvaluationController::class, 'storeResponse'])
        ->name('evaluations.responses.store');

    Route::put('/evaluations/{announcement}/responses/{response}', [EvaluationController::class, 'updateResponse'])
        ->name('evaluations.responses.update');

    Route::post('/evaluations/{announcement}/questions', [EvaluationController::class, 'storeQuestion'])
        ->name('evaluations.questions.store');

    Route::put('/evaluations/{announcement}/questions/{question}', [EvaluationController::class, 'updateQuestion'])
        ->name('evaluations.questions.update');

    Route::delete('/evaluations/{announcement}/questions/{question}', [EvaluationController::class, 'destroyQuestion'])
        ->name('evaluations.questions.destroy');

    Route::post('/evaluations/{announcement}/target', [EvaluationController::class, 'updateTarget'])
        ->name('evaluations.target');

    Route::post('/evaluations/{announcement}/finish', [EvaluationController::class, 'finish'])
        ->name('evaluations.finish');

    Route::get('/evaluations/{announcement}/summary', [EvaluationController::class, 'summary'])
        ->name('evaluations.summary');
});

Route::controller(OrganizationReportController::class)
    ->prefix('/student-org/reports')
    ->middleware('auth')
    ->group(function () {
        Route::get('/', 'index')->name('student_org.reports');
        Route::get('/{report}', 'show')->name('student_org.reports.show');
    });

Route::get('/student-org/notifications', [StudentOrgController::class, 'notifications'])
    ->middleware('auth')
    ->name('student_org.notifications');

/**
 * About Us — the organization profile a student org / college / department
 * owns and edits. Read-only viewing for other roles comes separately.
 */
Route::middleware('auth')->controller(OrgProfileController::class)->group(function () {
    Route::get('/student-org/about', 'edit')->name('org.about.edit');
    Route::post('/student-org/about', 'update')->name('org.about.update');
    Route::post('/student-org/about/contact', 'updateContact')->name('org.about.contact');
    Route::delete('/student-org/about/logo', 'removeLogo')->name('org.about.logo.remove');

    Route::post('/student-org/about/officers', 'storeOfficer')->name('org.about.officers.store');
    Route::post('/student-org/about/officers/{officer}', 'updateOfficer')->name('org.about.officers.update');
    Route::post('/student-org/about/officers/{officer}/avatar', 'updateOfficerAvatar')->name('org.about.officers.avatar.update');
    Route::delete('/student-org/about/officers/{officer}/avatar', 'removeOfficerAvatar')->name('org.about.officers.avatar.remove');
    Route::post('/student-org/about/officers/{officer}/move', 'moveOfficer')->name('org.about.officers.move');
    Route::delete('/student-org/about/officers/{officer}', 'destroyOfficer')->name('org.about.officers.destroy');
});

/*
 * Voluntary self-registration was removed on the CELP Head's instruction:
 * Student, Employee, Alumni and College/Department accounts are created by CELP
 * personnel, and Student Organization accounts by the CSA Director. The old
 * /register endpoints are gone rather than hidden, so a bookmarked or shared
 * link cannot be used to slip an unvetted account into the system.
 */

Route::get('/verify-email', function () {
    if (! session('verify_user_id')) {
        return redirect()->route('login')->withErrors('Please log in or register first.');
    }

    return view('verify_email', [
        'secondsLeft' => otp_seconds_left(session('verify_otp_expires_at')),
        'attemptsLeft' => OTP_MAX_ATTEMPTS - (int) session('verify_otp_attempts', 0),
        'maxAttempts' => OTP_MAX_ATTEMPTS,
    ]);
})->name('verify_email');

Route::post('/verify-email', function (Request $request) {
    $request->validate([
        'otp' => 'required|string',
    ]);

    $userId = session('verify_user_id');
    if (! $userId || ! session('verify_otp') || ! session('verify_otp_expires_at')) {
        return redirect()->route('login')->withErrors('Verification session expired. Please log in again.');
    }

    if (now()->greaterThan(session('verify_otp_expires_at'))) {
        session()->forget(['verify_user_id', 'verify_email', 'verify_otp', 'verify_otp_expires_at', 'verify_otp_attempts']);

        return redirect()->route('login')->withErrors('Verification code expired. Please log in again to request a new one.');
    }

    if ($request->otp !== session('verify_otp')) {
        $attempts = (int) session('verify_otp_attempts', 0) + 1;

        if ($attempts >= OTP_MAX_ATTEMPTS) {
            session()->forget(['verify_user_id', 'verify_email', 'verify_otp', 'verify_otp_expires_at', 'verify_otp_attempts']);

            return redirect()->route('login')->withErrors('Too many incorrect codes. Please log in again to request a new one.');
        }

        session(['verify_otp_attempts' => $attempts]);
        $left = OTP_MAX_ATTEMPTS - $attempts;

        return redirect()->route('verify_email')->withErrors([
            'otp' => "Invalid code. {$left} attempt".($left === 1 ? '' : 's').' remaining.',
        ]);
    }

    $user = User::find($userId);
    if (! $user) {
        return redirect()->route('login')->withErrors('Account not found.');
    }

    if (session('verify_email') !== $user->email) {
        session()->forget(['verify_user_id', 'verify_email', 'verify_otp', 'verify_otp_expires_at', 'verify_otp_attempts']);

        return redirect()->route('login')->withErrors(
            'The account email changed after this code was sent. Please log in again to receive a code at the current address.'
        );
    }

    $user->email_verified_at = now();
    $user->save();

    session()->forget(['verify_user_id', 'verify_email', 'verify_otp', 'verify_otp_expires_at', 'verify_otp_attempts']);

    return redirect()->route('login')->with('status', 'Email verified! You can now log in.');
})->name('verify_email.submit');

Route::post('/verify-email/resend', function (Request $request) {
    $userId = session('verify_user_id');
    if (! $userId) {
        return redirect()->route('login')->withErrors('Verification session expired. Please log in again.');
    }

    $user = User::find($userId);
    if (! $user) {
        return redirect()->route('login')->withErrors('Account not found.');
    }

    $sent = issue_email_verification_otp($user);

    $status = $sent
        ? "A new code was sent to {$user->email}."
        : "We couldn't send a code right now. Please try again in a moment.";

    return redirect()->route('verify_email')->with('status', $status);
})->name('verify_email.resend');

Route::get('/password/forgot', function () {
    return view('forgot_pass');
})->name('password.request');

Route::post('/password/forgot', function (Request $request) {
    $request->validate([
        'email' => ['required', 'email', 'exists:users,email'],
    ], [
        'email.email' => 'Please enter a valid email address.',
    ]);

    $user = User::where('email', $request->email)->first();
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    session([
        'reset_user_id' => $user->id,
        'reset_email' => $user->email,
        'reset_otp' => $otp,
        'reset_otp_expires_at' => now()->addMinutes(OTP_TTL_MINUTES),
        'reset_otp_attempts' => 0,
        'otp_verified' => false,
    ]);

    Mail::to($user->email)->send(new PasswordResetOtp($otp, $user->name));

    return redirect()->route('password.verify')->with('status', "OTP sent to {$user->email}. Please check your inbox.");
})->name('password.request.submit');

Route::get('/password/verify', function () {
    if (! session('reset_user_id')) {
        return redirect()->route('password.request')->withErrors('Please start password recovery first.');
    }

    return view('verify_otp', [
        'secondsLeft' => otp_seconds_left(session('reset_otp_expires_at')),
        'attemptsLeft' => OTP_MAX_ATTEMPTS - (int) session('reset_otp_attempts', 0),
        'maxAttempts' => OTP_MAX_ATTEMPTS,
    ]);
})->name('password.verify');

Route::post('/password/verify', function (Request $request) {
    $request->validate([
        'otp' => 'required|string',
    ]);

    if (! session('reset_user_id') || ! session('reset_otp') || ! session('reset_otp_expires_at')) {
        return redirect()->route('password.request')->withErrors('Password reset flow expired.');
    }

    if (now()->greaterThan(session('reset_otp_expires_at'))) {
        session()->forget(['reset_user_id', 'reset_email', 'reset_otp', 'reset_otp_expires_at', 'reset_otp_attempts', 'otp_verified']);

        return redirect()->route('password.request')->withErrors('OTP expired. Request a new code.');
    }

    if ($request->otp !== session('reset_otp')) {
        $attempts = (int) session('reset_otp_attempts', 0) + 1;

        if ($attempts >= OTP_MAX_ATTEMPTS) {
            session()->forget(['reset_user_id', 'reset_email', 'reset_otp', 'reset_otp_expires_at', 'reset_otp_attempts', 'otp_verified']);

            return redirect()->route('password.request')->withErrors('Too many incorrect codes. Request a new OTP.');
        }

        session(['reset_otp_attempts' => $attempts]);
        $left = OTP_MAX_ATTEMPTS - $attempts;

        return redirect()->route('password.verify')->withErrors([
            'otp' => "Invalid OTP. {$left} attempt".($left === 1 ? '' : 's').' remaining.',
        ]);
    }

    session(['otp_verified' => true]);

    return redirect()->route('password.reset')->with('status', 'OTP verified. You may reset your password now.');
})->name('password.verify.submit');

Route::get('/password/reset', function () {
    if (! session('reset_user_id') || ! session('otp_verified')) {
        return redirect()->route('password.request');
    }

    return view('reset_pass');
})->name('password.reset');

Route::post('/password/reset', function (Request $request) {
    $request->validate([
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ], [
        'password.confirmed' => 'Password confirmation does not match.',
    ]);

    $userId = session('reset_user_id');
    if (! $userId) {
        return redirect()->route('password.request')->withErrors('Reset session expired.');
    }

    $user = User::find($userId);
    if (! $user) {
        return redirect()->route('password.request')->withErrors('User not found.');
    }

    $user->password = Hash::make($request->password);
    $user->save();

    session()->forget(['reset_user_id', 'reset_email', 'reset_otp', 'reset_otp_expires_at', 'reset_otp_attempts', 'otp_verified']);

    return redirect()->route('login')->with('status', 'Password updated successfully. You may now log in.');
})->name('password.reset.submit');

Route::middleware('auth')->group(function () {
    Route::get('/users/create', function (Request $request) {
        $user = auth()->user();

        $allowedRoles = creatable_roles_for($user);

        if (empty($allowedRoles)) {
            abort(403, 'You are not authorized to create accounts.');
        }

        return view('create_user', [
            'allowedRoles' => $allowedRoles,
        ]);
    })->name('users.create');

    Route::get('/users', function (Request $request) {
        $user = $request->user();

        if ($user->role === 'celp_head') {
            // The system administrator sees every account.
            $users = User::with('creator')->get();
        } elseif ($manageable = manageable_roles_for($user)) {
            $users = User::with('creator')->whereIn('role', $manageable)->get();
        } else {
            abort(403, 'Not allowed');
        }

        return view('users.index', ['users' => $users]);
    })->name('users.index');

    Route::get('/users/{user}/edit', function (Request $request, User $user) {
        if (! can_edit_account($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        return view('users.edit', [
            'user' => $user,
            'canChangeEmail' => can_change_account_identity($request->user(), $user),
            'canChangeName' => can_change_account_identity($request->user(), $user),
        ]);
    })->name('users.edit');

    Route::post('/users/{user}/update', function (Request $request, User $user) {
        if (! can_edit_account($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        $canChangeEmail = can_change_account_identity($request->user(), $user);
        $canChangeName = can_change_account_identity($request->user(), $user);
        $rules = [
            'organization_name' => 'nullable|string|max:255',
            'org_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'remove_org_logo' => 'nullable|boolean',
            'org_description' => 'nullable|string|max:300',
        ];

        if ($user->role === 'participant') {
            $rules['id_number'] = [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'id_number')->ignore($user->id),
            ];
        }

        if ($canChangeEmail) {
            $rules['email'] = [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                'confirmed',
            ];
            if (strcasecmp(trim((string) $request->input('email')), $user->email) !== 0) {
                $rules['email'][] = 'dns';
            }
            if ($domains = allowed_account_email_domains()) {
                $rules['email'][] = 'ends_with:'.implode(',', array_map(fn ($domain) => '@'.$domain, $domains));
            }
        }

        if ($canChangeName) {
            $rules += [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'suffix' => 'nullable|string|max:50',
            ];
        }

        $validated = $request->validate($rules, [
            'email.email' => 'That email address does not exist or its domain cannot receive mail. Please check the spelling.',
            'email.confirmed' => 'The two email addresses do not match. Please retype it to confirm.',
            'email.ends_with' => 'This account must use an email address from an allowed domain.',
            'email.unique' => 'That email address is already registered to another account.',
            'id_number.required' => 'Please enter the participant’s institutional ID number.',
            'id_number.unique' => 'That ID number is already assigned to another account.',
        ]);

        $previousEmail = $user->email;
        $previousName = $user->name;
        $emailChanged = $canChangeEmail
            && strcasecmp($previousEmail, trim($validated['email'])) !== 0;
        $nameChanged = false;

        if ($canChangeName) {
            $firstName = trim($validated['first_name']);
            $middleName = isset($validated['middle_name']) ? trim($validated['middle_name']) : null;
            $lastName = isset($validated['last_name']) ? trim($validated['last_name']) : null;
            $suffix = isset($validated['suffix']) ? trim($validated['suffix']) : null;
            $middleName = $middleName !== '' ? $middleName : null;
            $lastName = $lastName !== '' ? $lastName : null;
            $suffix = $suffix !== '' ? $suffix : null;
            $newName = implode(' ', array_filter(
                [$firstName, $middleName, $lastName, $suffix],
                static fn ($part) => $part !== null && $part !== ''
            ));
            $nameChanged = $newName !== $previousName;

            if ($nameChanged) {
                $user->first_name = $firstName;
                $user->middle_name = $middleName;
                $user->last_name = $lastName;
                $user->suffix = $suffix;
                $user->name = $newName;
            }
        }

        if ($emailChanged) {
            $user->email = trim($validated['email']);
            $user->email_verified_at = null;
        }

        if ($request->hasFile('org_logo')) {
            if ($user->org_logo && ! preg_match('#^https?://#i', $user->org_logo) && Storage::disk('public')->exists($user->org_logo)) {
                Storage::disk('public')->delete($user->org_logo);
            }
            $user->org_logo = $request->file('org_logo')->store('org_logos', 'public');
        } elseif ($request->boolean('remove_org_logo')) {
            if ($user->org_logo && ! preg_match('#^https?://#i', $user->org_logo) && Storage::disk('public')->exists($user->org_logo)) {
                Storage::disk('public')->delete($user->org_logo);
            }
            $user->org_logo = null;
        }

        $idNumberChanged = $user->role === 'participant'
            && $user->id_number !== trim($validated['id_number']);
        $user->organization_name = $request->organization_name;
        $user->org_description = $request->org_description;
        if ($idNumberChanged) {
            $user->id_number = trim($validated['id_number']);
        }
        $user->save();

        if ($idNumberChanged) {
            AuditLog::record(
                'account.participant_id_changed',
                "Updated the participant ID number for {$user->name}.",
                $user,
                ['role' => $user->role]
            );
        }

        $status = match (true) {
            $emailChanged && $nameChanged => 'Account name and email updated. The account holder must verify the new address at their next sign-in.',
            $emailChanged => 'Email updated. The account holder must verify the new address at their next sign-in.',
            $nameChanged => 'Account name updated successfully.',
            $idNumberChanged => 'Participant ID number updated successfully.',
            default => 'User profile updated successfully.',
        };
        $emailNotificationWarning = null;

        if ($nameChanged) {
            AuditLog::record(
                'account.name_changed',
                "Changed the account name from {$previousName} to {$user->name}.",
                $user,
                ['old_name' => $previousName, 'new_name' => $user->name, 'role' => $user->role]
            );
        }

        if ($emailChanged) {
            AuditLog::record(
                'account.email_changed',
                "Changed the sign-in email for {$user->name}.",
                $user,
                ['old_email' => $previousEmail, 'new_email' => $user->email, 'role' => $user->role]
            );

            try {
                Mail::raw(
                    "The sign-in email for your {$user->role} account was changed to {$user->email}. "
                        .'If you did not expect this change, please contact the CELP office.',
                    fn ($message) => $message->to($previousEmail)->subject('Your account email was changed')
                );
            } catch (Throwable $e) {
                report($e);
                $emailNotificationWarning = "The email was changed, but we couldn't notify the previous address ({$previousEmail}).";
            }
        }

        if ($emailChanged && $request->user()->id === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                $emailNotificationWarning ? $status.' '.$emailNotificationWarning : $status
            );
        }

        return redirect()->route('users.edit', $user)
            ->with('status', $status)
            ->with('email_change_warning', $emailNotificationWarning);
    })->name('users.update');

    Route::post('/users/{user}/deactivate', function (Request $request, User $user) {
        if (! can_manage_account_status($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        $user->is_active = false;
        $user->save();

        AuditLog::record(
            'account.deactivated',
            "Deactivated the {$user->role} account for {$user->name}.",
            $user,
            ['email' => $user->email, 'role' => $user->role]
        );

        return redirect()->route('users.index')->with('status', 'Account deactivated successfully.');
    })->name('users.deactivate');

    Route::post('/users/{user}/activate', function (Request $request, User $user) {
        if (! can_manage_account_status($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        $user->is_active = true;
        $user->save();

        AuditLog::record(
            'account.reactivated',
            "Reactivated the {$user->role} account for {$user->name}.",
            $user,
            ['email' => $user->email, 'role' => $user->role]
        );

        return redirect()->route('users.index')->with('status', 'Account activated successfully.');
    })->name('users.activate');

    /**
     * Audit trail. Read-only by design — there is no edit or delete route, so the
     * record can't be quietly rewritten from inside the app.
     */
    Route::get('/audit-log', function (Request $request) {
        if ($request->user()->role !== 'celp_head') {
            abort(403, 'Not allowed');
        }

        $scope = $request->query('scope');
        $term = trim((string) $request->query('q', ''));

        $entries = AuditLog::query()
            ->with('actor')
            ->when(in_array($scope, ['account', 'project'], true),
                fn ($q) => $q->where('action', 'like', $scope.'.%'))
            ->when($term !== '', function ($q) use ($term) {
                $needle = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($needle) {
                    $inner->whereRaw('LOWER(summary) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(actor_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(subject_label, \'\')) LIKE ?', [$needle]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $scopeCounts = [
            'account' => AuditLog::where('action', 'like', 'account.%')->count(),
            'project' => AuditLog::where('action', 'like', 'project.%')->count(),
        ];

        return view('CELP.head.audit.index', [
            'entries' => $entries,
            'scope' => $scope,
            'term' => $term,
            'scopeCounts' => $scopeCounts,
            'totalEntries' => array_sum($scopeCounts),
        ]);
    })->name('audit.index');

    /**
     * Issue a fresh temporary password and email it.
     *
     * The recovery path for a credential email that never arrived. Since the
     * creator no longer chooses (or sees) the password, without this a bounced
     * email would leave the account permanently unreachable.
     */
    Route::post('/users/{user}/reset-password', function (Request $request, User $user) {
        if (! can_edit_account($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        $plainPassword = Str::password(14, symbols: false);
        $user->password = Hash::make($plainPassword);
        $user->must_change_password = true;
        $user->save();

        AuditLog::record(
            'account.password_reset',
            "Issued a new temporary password for {$user->name}.",
            $user,
            ['email' => $user->email, 'role' => $user->role]
        );

        try {
            Mail::to($user->email)->send(new AccountCreated([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ], $plainPassword));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'reset' => "The password was reset, but the email to {$user->email} could not be sent. Check the address and try again.",
            ]);
        }

        return back()->with('status', "A new temporary password was emailed to {$user->email}.");
    })->name('users.password.reset');

    Route::delete('/users/{user}/logo', function (Request $request, User $user) {
        if (! can_edit_account($request->user(), $user)) {
            abort(403, 'Not allowed');
        }

        if ($user->org_logo && ! preg_match('#^https?://#i', $user->org_logo) && Storage::disk('public')->exists($user->org_logo)) {
            Storage::disk('public')->delete($user->org_logo);
        }
        $user->org_logo = null;
        $user->save();

        return redirect()->route('users.edit', $user)->with('status', 'Organization logo removed.');
    })->name('users.logo.remove');

    // Placeholder routes for features referenced in dashboards
    Route::get('/proposals/review', function () {
        return view('admin.placeholder', ['title' => 'Proposal Review', 'description' => 'Initial proposal review queue (not implemented).']);
    })->name('proposals.review');

    Route::get('/proposals/second-review', [ProposalController::class, 'coordinatorIndex'])->name('proposals.second_review');
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
    Route::post('/proposals/{proposal}/coordinator-review', [ProposalController::class, 'coordinatorReview'])->name('proposals.coordinator_review');

    // Projects: submission by organization accounts
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::post('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    // Secretary, Coordinator, CELP Head & CSA Director review queues for projects
    Route::get('/projects/review', [ProjectController::class, 'secretaryIndex'])->name('projects.secretary_review');
    Route::get('/projects/second-review', [ProjectController::class, 'coordinatorIndex'])->name('projects.coordinator_review');
    /**
     * Organization directory — readable by every authenticated role, including
     * participants. This is the discovery surface for About Us profiles.
     */
    Route::get('/organizations', [OrganizationDirectoryController::class, 'index'])
        ->name('organizations.index');
    Route::get('/organizations/{organization}', [OrganizationDirectoryController::class, 'show'])
        ->whereNumber('organization')
        ->name('organizations.show');

    // Read-only activity directory for every non-participant role. Participants
    // are redirected to their own feed, which additionally lets them apply.
    Route::get('/activities', [ActivityDirectoryController::class, 'index'])->name('activities.index');
    // whereNumber keeps this wildcard from swallowing literal sibling paths
    // such as /activities/monitor, which is registered further down.
    Route::get('/activities/{announcement}', [ActivityDirectoryController::class, 'show'])
        ->whereNumber('announcement')
        ->name('activities.show');

    Route::get('/projects/all', [ProjectController::class, 'allIndex'])->name('projects.all');
    Route::get('/projects/third-review', [ProjectController::class, 'celpHeadIndex'])->name('projects.celp_head_review');
    Route::get('/projects/fourth-review', [ProjectController::class, 'csaDirectorIndex'])->name('projects.csa_review');
    Route::get('/projects/{project}/review', [ProjectController::class, 'showForReview'])->name('projects.show_review');
    Route::post('/projects/{project}/secretary-review', [ProjectController::class, 'secretaryReview'])->name('projects.secretary_review.update');
    Route::post('/projects/{project}/coordinator-review', [ProjectController::class, 'coordinatorReview'])->name('projects.coordinator_review.update');
    Route::post('/projects/{project}/celp-head-review', [ProjectController::class, 'celpHeadReview'])->name('projects.celp_head_review.update');
    Route::post('/projects/{project}/csa-review', [ProjectController::class, 'csaDirectorReview'])->name('projects.csa_review.update');

    Route::get('/evaluation/templates', function () {
        return redirect()->route('templates.manage');
    })->name('evaluation.templates');

    // Templates routes
    /*
     * Templates. Reading is open to every authenticated user — the forms are
     * what initiators and participants are meant to download. Writing is gated
     * by middleware on the group, so a handler added later inherits the check
     * instead of having to repeat it.
     */
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}/download', [TemplateController::class, 'download'])->name('templates.download');

    Route::middleware('can.manage.templates')->group(function () {
        Route::get('/templates/manage', [TemplateController::class, 'manage'])->name('templates.manage');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/{template}/replace', [TemplateController::class, 'replace'])->name('templates.replace');
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    });
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    // Global notifications (bell dropdown + full list) for all authenticated roles
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read_all');

    // Encoding itself belongs to the project initiator, so this staff entry
    // point lands on the evaluation list rather than on a form.
    Route::get('/evaluation/encode', [EvaluationController::class, 'index'])
        ->name('evaluation.encode');

    Route::get('/records', function () {
        return view('admin.placeholder', ['title' => 'Records & Archives', 'description' => 'Official records and historical archives.']);
    })->name('records.index');

    // Account password routes for logged-in users
    Route::get('/account/password', function () {
        return view('account.change_password');
    })->name('account.password')->middleware('auth');

    Route::post('/account/password', function (Request $request) {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;   // releases ForcePasswordChange
        $user->save();

        return redirect()->route(dashboard_route_for($request->user()))->with('status', 'Password updated.');
    })->name('account.password.update');

    Route::post('/account/profile-photo', [ProfilePhotoController::class, 'update'])
        ->name('account.profile-photo.update');
    Route::get('/account/profile-photo/view', [ProfilePhotoController::class, 'show'])
        ->name('account.profile-photo.show');
    Route::delete('/account/profile-photo', [ProfilePhotoController::class, 'destroy'])
        ->name('account.profile-photo.destroy');

    Route::get('/attendance', function () {
        return view('admin.placeholder', ['title' => 'Attendance', 'description' => 'Attendance view-only records.']);
    })->name('attendance.index');

    Route::get('/activities/monitor', function () {
        return view('admin.placeholder', ['title' => 'Activities Monitor', 'description' => 'Monitor ongoing, pending and approved activities.']);
    })->name('activities.monitor');

    Route::get('/evaluations/view', [EvaluationController::class, 'index'])
        ->name('evaluations.view');

    Route::controller(CelpStaffReportController::class)->group(function () {
        Route::get('/reports/coordinator', 'index')->name('reports.coordinator');
        Route::get('/reports/coordinator/{report}', 'show')->name('reports.coordinator.show');
        Route::get('/reports/secretary', 'index')->name('reports.secretary');
        Route::get('/reports/secretary/{report}', 'show')->name('reports.secretary.show');
    });

    Route::controller(CsaReportController::class)
        ->prefix('/csa-director/reports')
        ->name('csa.reports.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{report}', 'show')->name('show');
        });

    /**
     * CELP Head accomplishment reports. Each renders on screen, prints to PDF,
     * and exports CSV via ?format=csv.
     */
    Route::controller(ReportController::class)->group(function () {
        Route::get('/reports/operational', 'index')->name('reports.operational');
        Route::get('/reports/project-status', 'projectStatus')->name('reports.project_status');
        Route::get('/reports/activities', 'activities')->name('reports.activities');
        Route::get('/reports/attendance', 'attendance')->name('reports.attendance');
        Route::get('/reports/volunteers', 'volunteers')->name('reports.volunteers');
        Route::get('/reports/evaluations', 'evaluations')->name('reports.evaluations');
    });

    Route::post('/users/create', function (Request $request) {
        $user = auth()->user();

        $allowedRoles = array_keys(creatable_roles_for($user));

        if (empty($allowedRoles)) {
            abort(403, 'You are not authorized to create accounts.');
        }

        // Institutional and personal addresses are both accepted. `email:rfc,dns`
        // rejects a domain that cannot receive mail, and the retyped confirmation
        // catches a mistyped mailbox — the two together are what stand in for the
        // OTP step these staff-created accounts deliberately skip.
        $domains = allowed_account_email_domains();
        $emailRules = ['required', 'email:rfc,dns', 'max:255', 'unique:users,email', 'confirmed'];
        if ($domains) {
            $emailRules[] = 'ends_with:'.implode(',', array_map(fn ($d) => '@'.$d, $domains));
        }

        $domainList = implode(' or ', array_map(fn ($d) => '@'.$d, $domains));

        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'lastname' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => $emailRules,
            'role' => ['required', 'string', 'in:'.implode(',', $allowedRoles)],
            'organization_name' => 'nullable|string|max:255',
            'org_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'org_description' => 'nullable|string|max:300',

            // Student organizations must be classified; colleges/departments must not.
            'org_classification' => [
                Rule::requiredIf(fn () => $request->input('role') === 'student_org'),
                'nullable', Rule::in(array_keys(User::ORG_CLASSIFICATIONS)),
            ],

            // Participant classification and the fields each one carries.
            'participant_type' => [
                Rule::requiredIf(fn () => $request->input('role') === 'participant'),
                'nullable', Rule::in(array_keys(User::PARTICIPANT_TYPES)),
            ],
            'id_number' => [
                Rule::requiredIf(fn () => $request->input('role') === 'participant'),
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'id_number'),
            ],
            'affiliation' => [
                Rule::requiredIf(fn () => $request->input('role') === 'participant'),
                'nullable', 'string', 'max:255',
            ],
            // Students give their course; alumni the programme they graduated from.
            'program' => [
                Rule::requiredIf(fn () => in_array($request->input('participant_type'), ['student', 'alumni'], true)),
                'nullable', 'string', 'max:160',
            ],
            'employment_type' => [
                Rule::requiredIf(fn () => $request->input('participant_type') === 'employee'),
                'nullable', Rule::in(array_keys(User::EMPLOYMENT_TYPES)),
            ],
            'year_graduated' => [
                Rule::requiredIf(fn () => $request->input('participant_type') === 'alumni'),
                'nullable', 'integer', 'min:1900', 'max:'.now()->year,
            ],
        ], [
            'email.email' => 'That email address does not exist or its domain cannot receive mail. Please check the spelling.',
            'email.confirmed' => 'The two email addresses do not match. Please retype it to confirm.',
            'email.ends_with' => "Accounts must use an institutional address ending in {$domainList}.",
            'email.unique' => 'That email address is already registered to another account.',
            'password.confirmed' => 'Password confirmation does not match.',
            'org_classification.required' => 'Please choose whether this organization is co-curricular or extra-curricular.',
            'participant_type.required' => 'Please choose whether this is a Student, Employee or Alumni account.',
            'id_number.required' => 'Please enter the ID number for this participant.',
            'id_number.unique' => 'That ID number is already assigned to another account.',
            'affiliation.required' => 'Please enter the college, department or office.',
            'program.required' => 'Please enter the program or course.',
            'employment_type.required' => 'Please choose teaching or non-teaching.',
            'year_graduated.required' => 'Please enter the year this alumnus graduated.',
        ]);

        $middleName = trim((string) $request->input('middlename', ''));
        $middleName = $middleName !== '' ? $middleName : null;
        $fullName = implode(' ', array_filter([
            $request->firstname,
            $middleName,
            $request->lastname,
        ], static fn ($part) => $part !== null && $part !== ''));

        // Generated, not chosen by the creator: nobody but the account holder
        // should ever know this, and it is replaced at first login anyway.
        $plainPassword = Str::password(14, symbols: false);

        $orgLogoPath = null;
        if ($request->hasFile('org_logo')) {
            $orgLogoPath = $request->file('org_logo')->store('org_logos', 'public');
        }

        $created = User::create([
            'username' => $request->username,
            'name' => $fullName,
            'first_name' => $request->firstname,
            'middle_name' => $middleName,
            'last_name' => $request->lastname,
            'suffix' => $request->suffix,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
            'role' => $request->role,
            'created_by' => $user->id,
            'organization_name' => $request->organization_name,
            'org_logo' => $orgLogoPath,
            'org_description' => $request->org_description,
            // Classification/detail fields only apply to their own account type;
            // the form hides the rest, and validation nulls them out here.
            'org_classification' => $request->input('role') === 'student_org'
                ? $request->input('org_classification') : null,
            'participant_type' => $request->input('role') === 'participant'
                ? $request->input('participant_type') : null,
            'id_number' => $request->input('role') === 'participant' ? $request->input('id_number') : null,
            'affiliation' => $request->input('role') === 'participant' ? $request->input('affiliation') : null,
            'program' => in_array($request->input('participant_type'), ['student', 'alumni'], true)
                ? $request->input('program') : null,
            'employment_type' => $request->input('participant_type') === 'employee'
                ? $request->input('employment_type') : null,
            'year_graduated' => $request->input('participant_type') === 'alumni'
                ? $request->input('year_graduated') : null,
            'is_active' => true,
            // Admin-vouched accounts skip the OTP challenge: the creating
            // admin already confirms the recipient's identity and role.
            'email_verified_at' => now(),
        ]);

        AuditLog::record(
            'account.created',
            "Created a {$created->role} account for {$created->name}.",
            $created,
            ['email' => $created->email, 'role' => $created->role]
        );

        // The account exists either way, but whether its password actually
        // reached the recipient is the creator's problem to know about — a
        // silent failure here used to look identical to success.
        $mailed = true;
        $mailError = null;
        try {
            Mail::to($created->email)->send(new AccountCreated([
                'name' => $created->name,
                'email' => $created->email,
                'role' => $created->role,
            ], $plainPassword));
        } catch (Throwable $e) {
            report($e);
            $mailed = false;
            $mailError = $e->getMessage();
        }

        return redirect()->route('users.created')->with([
            'created_user_id' => $created->id,
            'created_mailed' => $mailed,
            'created_mail_error' => $mailError,
        ]);
    })->name('users.store');

    /**
     * Post-creation confirmation. A separate GET (rather than rendering from the
     * POST) so a refresh can't re-submit the form.
     */
    Route::get('/users/created', function (Request $request) {
        $createdId = session('created_user_id');
        if (! $createdId) {
            return redirect()->route('users.index');
        }

        $created = User::find($createdId);
        if (! $created) {
            return redirect()->route('users.index');
        }

        // Only the creator (or a CELP Head) may see the confirmation.
        $auth = $request->user();
        abort_unless($created->created_by === $auth->id || $auth->role === 'celp_head', 403);

        return view('users.created', [
            'created' => $created,
            'mailed' => (bool) session('created_mailed', true),
            'mailError' => session('created_mail_error'),
        ]);
    })->name('users.created');
});
