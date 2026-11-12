<?php

namespace App\Models;

use Database\Factories\EnquiryActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A follow-up call or an admission step of an enquiry, with who recorded it.
 */
class EnquiryActivity extends Model
{
    /** @use HasFactory<EnquiryActivityFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(AdmissionEnquiry::class, 'admission_enquiry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
