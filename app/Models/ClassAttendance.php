<?php

namespace App\Models;

use App\Enums\ClassAttendanceStatus;
use Database\Factories\ClassAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether a student attended the school on a day. Attendance follows the class, not the subjects.
 */
class ClassAttendance extends Model
{
    /** @use HasFactory<ClassAttendanceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => ClassAttendanceStatus::class,
        ];
    }
}
