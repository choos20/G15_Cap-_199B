<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One respondent's completed form.
 *
 * @property int         $id
 * @property int         $evaluation_id
 * @property string      $respondent_type
 * @property string      $source
 * @property string|null $heard_about
 * @property string|null $suggestions
 * @property string|null $observations
 * @property string|null $volunteer_interest
 * @property int|null    $encoded_by
 */
class EvaluationResponse extends Model
{
    use HasFactory;

    /** Encoded from a paper form by the project initiator. */
    public const SOURCE_PAPER = 'paper';

    /** Reserved for an on-site digital form; nothing writes this yet. */
    public const SOURCE_QR = 'qr';

    public const SOURCE_LABELS = [
        self::SOURCE_PAPER => 'Encoded from paper',
        self::SOURCE_QR => 'Scanned QR form',
    ];

    /** Answers to "How did the respondent hear about the activity?" */
    public const HEARD_ABOUT = [
        'hnu_announcement' => 'HNU / CELP announcement',
        'social_media' => 'Social media',
        'flyers_posters' => 'Flyers / posters',
        'other' => 'Other',
    ];

    public const HEARD_ABOUT_BY_TYPE = [
        'beneficiary' => self::HEARD_ABOUT,
        'implementer' => [
            'celp_announcement' => 'CELP announcement',
            'social_media' => 'Social media',
            'flyers_posters' => 'Flyers / posters',
            'organization_initiative' => "Organization's initiative",
        ],
    ];

    public static function heardAboutOptions(string $respondentType): array
    {
        return self::HEARD_ABOUT_BY_TYPE[$respondentType] ?? self::HEARD_ABOUT;
    }

    public static function heardAboutLabels(): array
    {
        return array_merge(...array_values(self::HEARD_ABOUT_BY_TYPE));
    }

    protected $fillable = [
        'evaluation_id',
        'respondent_type',
        'source',
        'heard_about',
        'heard_about_sources',
        'volunteer_interest',
        'suggestions',
        'observations',
        'encoded_by',
    ];

    protected function casts(): array
    {
        return ['heard_about_sources' => 'array'];
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function ratings()
    {
        return $this->hasMany(EvaluationResponseRating::class, 'response_id');
    }

    public function customAnswers()
    {
        return $this->hasMany(EvaluationCustomAnswer::class, 'response_id');
    }

    public function encoder()
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function heardAboutLabel(): ?string
    {
        return $this->heard_about
            ? (self::HEARD_ABOUT[$this->heard_about] ?? $this->heard_about)
            : null;
    }
}
