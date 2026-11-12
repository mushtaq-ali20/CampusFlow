<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A document shared with the staff: a file in a folder, shared with some roles (and teachers), at one branch or all
 * of them, visible only from a date and until a date if they are set. Files are kept on the private disk.
 */
class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity;

    /**
     * The audience of the teachers, who have no role.
     */
    public const TEACHERS = 'teacher';

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile()->useDisk('local');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(DocumentAudience::class);
    }

    /**
     * @param  list<string>  $audiences
     */
    public function shareWith(array $audiences): void
    {
        $this->audiences()->whereNotIn('audience', $audiences)->delete();

        foreach ($audiences as $audience) {
            $this->audiences()->firstOrCreate(['audience' => $audience]);
        }
    }

    /**
     * The audiences a user belongs to: their roles, and "teacher" for a teacher.
     *
     * @return list<string>
     */
    public static function audiencesOf(User $user): array
    {
        return [...$user->getRoleNames()->all(), ...($user->isTeacher() ? [self::TEACHERS] : [])];
    }

    /**
     * The documents a user sees. Staff who manage documents see all the documents of the branches they work on;
     * the others see the documents shared with one of their roles, at their branch or all of them, while they are
     * visible.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $branchIds = $user->activeBranchId() !== null
            ? [$user->activeBranchId()]
            : ($user->isTeacher() ? $user->teacher->campuses()->pluck('campuses.id')->all() : null);

        $query->when($branchIds !== null, fn (Builder $query) => $query->where(fn (Builder $documents) => $documents
            ->whereNull('documents.campus_id')
            ->orWhereIn('documents.campus_id', $branchIds)));

        if ($user->can('documents.manage')) {
            return;
        }

        $today = now()->toDateString();

        $query->whereHas('audiences', fn (Builder $audiences) => $audiences->whereIn('audience', self::audiencesOf($user)))
            ->where(fn (Builder $documents) => $documents->whereNull('visible_from')->orWhereDate('visible_from', '<=', $today))
            ->where(fn (Builder $documents) => $documents->whereNull('visible_until')->orWhereDate('visible_until', '>=', $today));
    }

    /**
     * Whether the document is shown today to those it is shared with: "scheduled", "visible" or "expired".
     */
    public function visibility(): string
    {
        return match (true) {
            $this->visible_from !== null && $this->visible_from->isFuture() => 'scheduled',
            $this->visible_until !== null && $this->visible_until->endOfDay()->isPast() => 'expired',
            default => 'visible',
        };
    }

    protected function casts(): array
    {
        return [
            'visible_from' => 'date',
            'visible_until' => 'date',
        ];
    }
}
