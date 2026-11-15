<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pin_id', 'device_hash'])]
class Report extends Model
{
    public const DELETES_AT = 5;

    public function pin(): BelongsTo
    {
        return $this->belongsTo(Pin::class);
    }
}
