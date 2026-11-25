<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['discord_thread_id', 'trello_card_id', 'tags'])]
class TrelloBugCard extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
