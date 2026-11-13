<?php

namespace App\Models;

use App\Events\UserDeleting;
use App\Services\AccessModules;
use App\Services\ActiveBranch;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * Anyone with a role, or a teacher profile, uses the staff screens, unless they were deactivated.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && ($this->roles->isNotEmpty() || $this->isTeacher());
    }

    /**
     * Whether the user may sign in, whether they must change their password, and their last sign-in.
     */
    public function accessState(): HasOne
    {
        return $this->hasOne(UserAccessState::class);
    }

    /**
     * A deactivated user cannot sign in; their records are kept.
     */
    public function isActive(): bool
    {
        return $this->accessState?->is_active ?? true;
    }

    public function mustChangePassword(): bool
    {
        return $this->accessState?->must_change_password ?? false;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(AccessModules::ADMINISTRATOR);
    }

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $dispatchesEvents = [
        'deleting' => UserDeleting::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function isTeacher()
    {
        return Teacher::whereId($this->id)->count() > 0;
    }

    public function isStudent()
    {
        return Student::whereId($this->id)->count() > 0;
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'id', 'id');
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class, 'id', 'id');
    }

    /**
     * The HR file of an employee (staff or teacher).
     */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * The branch of a staff member who only works for one branch. Staff without a branch work across all branches.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * The branch whose records this user works on: their own branch for branch staff, else the branch chosen in
     * the branch switcher when this is the signed-in user, else null for all branches.
     */
    public function activeBranchId(): ?int
    {
        if ($this->campus_id !== null) {
            return (int) $this->campus_id;
        }

        return $this->is(auth()->user()) ? app(ActiveBranch::class)->id() : null;
    }

    public function getFirstnameAttribute($value): string
    {
        return Str::title($value);
    }

    public function getLastnameAttribute($value): string
    {
        return Str::upper($value);
    }

    public function getNameAttribute(): string
    {
        return $this->firstname.' '.$this->lastname;
    }

    /**
     * Emails are unique and used to log in. They are stored in lower case, and an empty email is stored as null,
     * so that users without email do not conflict.
     */
    public function setEmailAttribute(?string $value): void
    {
        $value = trim((string) $value);

        $this->attributes['email'] = $value === '' ? null : Str::lower($value);
    }
}
