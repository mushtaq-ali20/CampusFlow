<?php

namespace App\Models;

use App\Enums\PtmMeetingStatus;
use Database\Factories\PtmMeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The time slot of the parents of a student with the class teacher on a meeting day, with the notes of the meeting
 * and the actions agreed.
 */
class PtmMeeting extends Model
{
    /** @use HasFactory<PtmMeetingFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PtmEvent::class, 'ptm_event_id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Whether actions were agreed and are not done yet.
     */
    public function hasOpenAction(): bool
    {
        return filled($this->action_items) && $this->action_done_at === null;
    }

    protected function casts(): array
    {
        return [
            'status' => PtmMeetingStatus::class,
            'action_due_on' => 'date',
            'action_done_at' => 'datetime',
        ];
    }
}
