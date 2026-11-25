<?php

namespace App\Models;

use App\Support\ChangelogMarkdown;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'body', 'published_at', 'discord_channel_id', 'discord_message_id', 'discord_is_thread'])]

class ChangelogEntry extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'discord_is_thread' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function html(): string
    {
        return ChangelogMarkdown::html($this->body);
    }

    public function publishedLabel(): string
    {
        $at = $this->published_at?->timezone('Europe/Sofia');

        if ($at === null) {
            return '';
        }

        $months = [
            1 => 'януари',
            2 => 'февруари',
            3 => 'март',
            4 => 'април',
            5 => 'май',
            6 => 'юни',
            7 => 'юли',
            8 => 'август',
            9 => 'септември',
            10 => 'октомври',
            11 => 'ноември',
            12 => 'декември',
        ];

        return $at->format('j').' '.$months[(int) $at->format('n')].' '.$at->format('Y');
    }
}
