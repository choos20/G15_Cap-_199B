<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A rating item on the beneficiary evaluation form.
 *
 * @property int    $id
 * @property string $label
 * @property string $short_label
 * @property int    $sort_order
 * @property bool   $is_active
 */
class EvaluationCriterion extends Model
{
    use HasFactory;

    /** Laravel would otherwise pluralise this to "evaluation_criterions". */
    protected $table = 'evaluation_criteria';

    /** The printed scale: 1 Poor to 5 Outstanding. */
    public const SCALE = [
        1 => 'Poor',
        2 => 'Fair',
        3 => 'Good',
        4 => 'Very Good',
        5 => 'Excellent',
    ];

    protected $fillable = ['label', 'short_label', 'sort_order', 'is_active', 'respondent_type'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Criteria offered on a new form, in printed order. Retired criteria are
     * excluded here but still resolve when reading back past responses.
     */
    public static function active(string $respondentType = 'beneficiary')
    {
        return static::query()
            ->where('is_active', true)
            ->where('respondent_type', $respondentType)
            ->orderBy('sort_order')
            ->get();
    }

    public function ratings()
    {
        return $this->hasMany(EvaluationResponseRating::class, 'criterion_id');
    }
}
