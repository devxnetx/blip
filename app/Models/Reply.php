<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pin_id', 'device_hash', 'message', 'author_only'])]
class Reply extends Model
{
    public const MAX_LENGTH = 200;

    protected function casts(): array
    {
        return [
            'author_only' => 'boolean',
        ];
    }

    public function pin(): BelongsTo
    {
        return $this->belongsTo(Pin::class);
    }

    /**
     * @return array{id: int, message: string, created_at: string, author_only: bool, mine: bool}
     */
    public function toApi(bool $mine = false): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'created_at' => $this->created_at->toIso8601String(),
            'author_only' => $this->author_only,
            'mine' => $mine,
        ];
    }
}
