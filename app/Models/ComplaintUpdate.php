<?php

namespace App\Models;

use Database\Factories\ComplaintUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened to a complaint: a comment, an assignment, an escalation or the resolution. Updates written
 * by the escalation have no user.
 */
class ComplaintUpdate extends Model
{
    /** @use HasFactory<ComplaintUpdateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
