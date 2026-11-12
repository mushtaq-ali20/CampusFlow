<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ParentMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A WhatsApp message prepared for the parents of a student, and what it was about, such as a discipline record.
 * The school sends it from its own WhatsApp: this is the copy kept by CampusFlow.
 */
class ParentMessage extends Model
{
    /** @use HasFactory<ParentMessageFactory> */
    use BelongsToBranch, HasFactory;

    protected $guarded = ['id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
