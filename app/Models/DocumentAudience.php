<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who a document is shared with: a role name, or "teacher" for the teachers.
 */
class DocumentAudience extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
