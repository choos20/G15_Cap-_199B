<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string $email
 * @property string $password
 * @property string $role
 * @property bool $must_change_password
 * @property int|null $created_by
 * @property string|null $organization_name
 * @property string|null $org_logo
 * @property string|null $profile_photo_path
 * @property string|null $org_description
 * @property string|null $id_number
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read User|null $creator
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'role',
        'created_by',
        'organization_name',
        'org_logo',
        'profile_photo_path',
        'profile_photo_data',
        'profile_photo_mime',
        'org_logo_data',
        'org_logo_mime',
        'org_description',
        'org_acronym',
        'org_college',
        'org_year_founded',
        'org_vision',
        'org_mission',
        'org_goals',
        'org_advocacies',
        'org_contact_email',
        'org_facebook',
        'org_profile_updated_at',
        'is_active',
        'must_change_password',
        'email_verified_at',
        'id_type',
        'id_number',
        'affiliation',
        'participant_type',
        'program',
        'employment_type',
        'year_graduated',
        'org_classification',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'org_advocacies' => 'array',
            'org_profile_updated_at' => 'datetime',
        ];
    }

    /**
     * Advocacy areas an organization can claim on its About Us profile.
     * Fixed list so participants can filter the directory by them later.
     * An organization may claim as many as genuinely apply to its work.
     */
    public const ADVOCACY_AREAS = [
        'education' => 'Education',
        'technology_literacy' => 'Technology literacy',
        'environment' => 'Environment',
        'health' => 'Health',
        'livelihood' => 'Livelihood',
        'disaster_response' => 'Disaster response',
        'youth_development' => 'Youth development',
    ];

    /**
     * Participants are classified rather than split into separate roles, so the
     * volunteer feed, applications, scanner and attendance stay on one path.
     */
    public const PARTICIPANT_TYPES = [
        'student' => 'Student',
        'employee' => 'Employee',
        'alumni' => 'Alumni',
    ];

    public const EMPLOYMENT_TYPES = [
        'teaching' => 'Teaching',
        'non_teaching' => 'Non-teaching',
    ];

    /**
     * Roles allowed to upload, replace and remove proposal templates.
     *
     * Templates are official CELP documents (waivers, permits, proposal and
     * narrative report forms), so only CELP personnel and the CSA Director may
     * change them. Everyone else downloads.
     *
     * This is the single definition — the middleware, the controller and the
     * sidebar all ask canManageTemplates() rather than repeating the list.
     */
    public const TEMPLATE_MANAGER_ROLES = [
        'celp_head',
        'coordinator',
        'celp_secretary',
        'csa_director',
    ];

    public function canManageTemplates(): bool
    {
        return in_array($this->role, self::TEMPLATE_MANAGER_ROLES, true);
    }

    /** Required when the CSA Director registers a student organization. */
    public const ORG_CLASSIFICATIONS = [
        'co_curricular' => 'Co-curricular',
        'extra_curricular' => 'Extra-curricular',
    ];

    public function participantTypeLabel(): ?string
    {
        return self::PARTICIPANT_TYPES[$this->participant_type] ?? null;
    }

    public function employmentTypeLabel(): ?string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? null;
    }

    public function orgClassificationLabel(): ?string
    {
        return self::ORG_CLASSIFICATIONS[$this->org_classification] ?? null;
    }

    /**
     * What the ID number is called for this account — the three participant
     * types each carry a differently-named ID.
     */
    public function idNumberLabel(): string
    {
        return match ($this->participant_type) {
            'student' => 'Student ID No.',
            'employee' => 'Employee ID No.',
            'alumni' => 'Alumni ID No.',
            default => 'ID number',
        };
    }

    /**
     * What the affiliation field means for this account.
     */
    public function affiliationLabel(): string
    {
        return $this->participant_type === 'employee'
            ? 'College / Department or Office'
            : 'College / Department';
    }

    /**
     * The initiator taxonomy used by reporting: student organizations report as
     * their classification, colleges and departments as themselves.
     */
    public function initiatorTypeLabel(): ?string
    {
        return match ($this->role) {
            'student_org' => $this->orgClassificationLabel() ?? 'Student Organization',
            'college_department' => 'College / Department',
            default => null,
        };
    }

    public function officers()
    {
        return $this->hasMany(OrgOfficer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    /**
     * Browser-usable URL for the organization logo, or null when there isn't one.
     *
     * `org_logo` holds a relative disk path ("org_logos/abc.png"), which is not a
     * valid src on its own — rendering it raw produces a broken image. Some rows
     * hold an absolute URL instead, so both shapes are resolved here rather than
     * being re-derived in every view that shows a logo.
     */
    public function orgLogoUrl(): ?string
    {
        if ($this->org_logo_data) {
            return $this->imageDataUrl($this->org_logo_data, $this->org_logo_mime);
        }

        if (! $this->org_logo) {
            return null;
        }

        return preg_match('#^https?://#i', $this->org_logo)
            ? $this->org_logo
            : Storage::disk('public')->url($this->org_logo);
    }

    public function profilePhotoUrl(): ?string
    {
        if ($this->profile_photo_data || $this->profile_photo_path) {
            return route('account.profile-photo.show', ['user' => $this->getKey()]);
        }

        return null;
    }

    private function imageDataUrl(mixed $data, ?string $mime): string
    {
        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }

        return 'data:' . ($mime ?: 'application/octet-stream') . ';base64,' . base64_encode((string) $data);
    }

    /** Goals are stored one per line; split for display. */
    public function goalList(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->org_goals))
            ->map(fn ($g) => trim($g))
            ->filter()
            ->values()
            ->all();
    }

    public function advocacyLabels(): array
    {
        return collect($this->org_advocacies ?? [])
            ->map(fn ($k) => self::ADVOCACY_AREAS[$k] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * How much of the About Us profile is filled in, as a percentage.
     * Drives the completeness pill and the "to reach 100%" hints.
     */
    public function profileCompleteness(): array
    {
        $checks = [
            'Add a logo' => filled($this->org_logo),
            'Write a short description' => filled($this->org_description),
            'Add your college or department' => filled($this->org_college),
            'Add your vision' => filled($this->org_vision),
            'Add your mission' => filled($this->org_mission),
            'List your goals' => count($this->goalList()) > 0,
            'Choose advocacy areas' => count($this->org_advocacies ?? []) > 0,
            'Add your officers' => $this->role === 'student_org' || $this->officers()->exists(),
            'Add a contact email' => filled($this->org_contact_email),
        ];

        $done = count(array_filter($checks));

        return [
            'percent' => (int) round($done / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok)),
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function volunteerApplications()
    {
        return $this->hasMany(VolunteerApplication::class);
    }
}
