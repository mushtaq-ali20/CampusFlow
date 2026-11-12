<?php

namespace App\Services;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintUpdate;
use App\Models\User;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Complaints from the time they are logged to their resolution. The category gives the time limit, shortened or
 * lengthened by the priority, and who handles a new complaint. When the time limit passes, the complaint is
 * escalated to the escalation contact of the category, with a new time limit. Whoever receives a complaint is
 * notified in CampusFlow.
 */
class ComplaintService
{
    /**
     * Logs a complaint and gives it to the person who handles its category.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user): Complaint
    {
        $category = ComplaintCategory::query()->findOrFail($data['complaint_category_id']);
        $priority = $data['priority'] instanceof ComplaintPriority ? $data['priority'] : ComplaintPriority::from($data['priority']);

        $complaint = Complaint::query()->create([
            ...$data,
            'priority' => $priority,
            'status' => ComplaintStatus::New,
            'assigned_to' => $data['assigned_to'] ?? $category->assign_to_id,
            'due_at' => now()->addHours($priority->hoursFor($category->response_hours)),
            'created_by' => $user?->id,
        ]);

        $this->log($complaint, __('Complaint logged. To be resolved by :date.', ['date' => $complaint->due_at->format('d M Y H:i')]), $user);
        $this->notifyAssignee($complaint, __('New complaint :number', ['number' => $complaint->complaint_number]));

        return $complaint;
    }

    public function log(Complaint $complaint, string $note, ?User $user): ComplaintUpdate
    {
        return $complaint->updates()->create(['note' => $note, 'user_id' => $user?->id]);
    }

    public function assign(Complaint $complaint, User $assignee, ?User $user, ?string $note = null): Complaint
    {
        $complaint->update([
            'assigned_to' => $assignee->id,
            'status' => $complaint->status === ComplaintStatus::New ? ComplaintStatus::InProgress : $complaint->status,
        ]);

        $this->log($complaint, trim(__('Assigned to :name.', ['name' => $assignee->name]).' '.$note), $user);
        $this->notifyAssignee($complaint, __('Complaint :number assigned to you', ['number' => $complaint->complaint_number]));

        return $complaint;
    }

    /**
     * Adds a comment. A new complaint is then in progress.
     */
    public function comment(Complaint $complaint, string $note, ?User $user): Complaint
    {
        if ($complaint->status === ComplaintStatus::New) {
            $complaint->update(['status' => ComplaintStatus::InProgress]);
        }

        $this->log($complaint, $note, $user);

        return $complaint;
    }

    public function resolve(Complaint $complaint, string $resolution, ?User $user): Complaint
    {
        if (! $complaint->status->isOpen()) {
            throw new DomainException(__('This complaint is already resolved.'));
        }

        $complaint->update(['status' => ComplaintStatus::Resolved, 'resolved_at' => now(), 'resolution' => $resolution]);
        $this->log($complaint, __('Resolved: :resolution', ['resolution' => $resolution]), $user);

        return $complaint;
    }

    /**
     * Opens a resolved complaint again, with a new time limit.
     */
    public function reopen(Complaint $complaint, string $reason, ?User $user): Complaint
    {
        if ($complaint->status->isOpen()) {
            throw new DomainException(__('This complaint is still open.'));
        }

        $complaint->update([
            'status' => ComplaintStatus::InProgress,
            'resolved_at' => null,
            'due_at' => now()->addHours($complaint->priority->hoursFor($complaint->category->response_hours)),
        ]);
        $this->log($complaint, __('Reopened: :reason', ['reason' => $reason]), $user);
        $this->notifyAssignee($complaint, __('Complaint :number reopened', ['number' => $complaint->complaint_number]));

        return $complaint;
    }

    /**
     * Escalates the open complaints past their time limit: they go to the escalation contact of their category (or
     * stay with their assignee when the category has none), with a new time limit. Returns how many were escalated.
     */
    public function escalateOverdue(): int
    {
        $escalated = 0;

        Complaint::query()
            ->open()
            ->where('due_at', '<', now())
            ->with('category')
            ->each(function (Complaint $complaint) use (&$escalated): void {
                $complaint->update([
                    'status' => ComplaintStatus::Escalated,
                    'escalation_level' => $complaint->escalation_level + 1,
                    'assigned_to' => $complaint->category->escalate_to_id ?? $complaint->assigned_to,
                    'due_at' => now()->addHours($complaint->priority->hoursFor($complaint->category->response_hours)),
                ]);

                $this->log($complaint, __('Escalated (level :level): the time limit passed.', ['level' => $complaint->escalation_level]), null);
                $this->notifyAssignee($complaint, __('Complaint :number escalated to you', ['number' => $complaint->complaint_number]));
                $escalated++;
            });

        return $escalated;
    }

    private function notifyAssignee(Complaint $complaint, string $title): void
    {
        $assignee = $complaint->assignee()->first();

        if ($assignee === null) {
            return;
        }

        try {
            $url = ComplaintResource::getUrl('view', ['record' => $complaint]);
        } catch (Throwable) {
            $url = null;
        }

        Notification::make()
            ->title($title)
            ->body($complaint->subject.' · '.__('Resolve by :date', ['date' => $complaint->due_at->format('d M Y H:i')]))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->actions($url ? [Action::make('view')->label(__('Open'))->url($url)->markAsRead()] : [])
            ->sendToDatabase($assignee);
    }
}
