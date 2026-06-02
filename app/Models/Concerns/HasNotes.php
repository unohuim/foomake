<?php

namespace App\Models\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds an explicit tenant-scoped polymorphic notes relationship to supported resources.
 */
trait HasNotes
{
    /**
     * Get notes attached to this resource.
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
