<?php

namespace App\Models;

use App\Enums\ExamType;
use Database\Factories\SubjectResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The marks a student obtained in a subject in one of the three exams of the academic year of the admission.
 */
class SubjectResult extends Model
{
    /** @use HasFactory<SubjectResultFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    protected function casts(): array
    {
        return [
            'exam' => ExamType::class,
            'obtained_marks' => 'float',
        ];
    }
}
