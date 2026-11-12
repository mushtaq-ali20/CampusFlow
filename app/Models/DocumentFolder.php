<?php

namespace App\Models;

use Database\Factories\DocumentFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder of shared documents, such as Policies or Circulars.
 */
class DocumentFolder extends Model
{
    /** @use HasFactory<DocumentFolderFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
