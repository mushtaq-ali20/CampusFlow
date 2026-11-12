<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\PtmMeetingStatus;
use App\Models\Admission;
use App\Models\PtmEvent;
use App\Models\PtmMeeting;
use App\Models\Section;
use Illuminate\Support\Carbon;

/**
 * Parent teacher meetings. Each section of a meeting day has its own row of time slots with its class teacher, from
 * the start time of the day, one slot per student in alphabetical order.
 */
class PtmService
{
    /**
     * Gives a time slot to every active student of the sections of the meeting day who has none yet, after the
     * slots already given in their section, and removes the slots not yet held of sections no longer in the day.
     * Returns how many slots were created.
     */
    public function generateMeetings(PtmEvent $event): int
    {
        $sectionIds = $event->sections()->pluck('sections.id');

        $event->meetings()
            ->whereNotIn('section_id', $sectionIds)
            ->whereIn('status', [PtmMeetingStatus::Scheduled, PtmMeetingStatus::Invited, PtmMeetingStatus::Confirmed])
            ->delete();

        $created = 0;

        foreach ($event->sections()->get() as $section) {
            /** @var Section $section */
            $existing = $event->meetings()->where('section_id', $section->id)->get();
            $scheduled = $existing->pluck('admission_id');
            $next = $existing->count();

            $admissions = Admission::query()
                ->with('student')
                ->where('section_id', $section->id)
                ->where('status', AdmissionStatus::Active)
                ->whereNotIn('id', $scheduled)
                ->get()
                ->sortBy(fn (Admission $admission): string => $admission->student->name);

            foreach ($admissions as $admission) {
                $event->meetings()->create([
                    'admission_id' => $admission->id,
                    'section_id' => $section->id,
                    'teacher_id' => $section->class_teacher_id,
                    'slot_time' => $this->slotTime($event, $next++),
                    'status' => PtmMeetingStatus::Scheduled,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * The start time of the slot of the given position (from 0) in a section.
     */
    public function slotTime(PtmEvent $event, int $position): string
    {
        return Carbon::parse($event->starts_at)->addMinutes($position * $event->slot_minutes)->format('H:i:s');
    }

    /**
     * The WhatsApp invitation of the parents to their meeting.
     */
    public function invitation(PtmMeeting $meeting): string
    {
        $event = $meeting->event;
        $lines = [
            __('Dear parent,'),
            __('You are invited to the :title on :date at :time to meet the class teacher of :student (:section).', [
                'title' => $event->title,
                'date' => $event->date->translatedFormat('l d F Y'),
                'time' => Carbon::parse($meeting->slot_time)->format('h:i A'),
                'student' => $meeting->admission->student->name,
                'section' => $meeting->section->full_name,
            ]),
            __('Please reply to confirm.'),
            (string) config('campusflow.company_name'),
        ];

        return implode("\n\n", $lines);
    }

    /**
     * Records the meeting held, with what was discussed and the actions agreed.
     */
    public function recordMeeting(PtmMeeting $meeting, ?string $notes, ?string $actionItems, ?string $actionDueOn): PtmMeeting
    {
        $meeting->update([
            'status' => PtmMeetingStatus::Met,
            'notes' => $notes,
            'action_items' => $actionItems,
            'action_due_on' => filled($actionItems) ? $actionDueOn : null,
            'action_done_at' => null,
        ]);

        return $meeting;
    }
}
