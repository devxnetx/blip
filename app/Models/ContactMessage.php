<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'reason', 'message', 'read_at'])]
class ContactMessage extends Model
{
    /**
     * @var array<string, string>
     */
    public const REASONS = [
        'map' => 'Нещо на картата',
        'pin' => 'Пин трябва да се махне',
        'question' => 'Въпрос',
        'other' => 'Друго',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? (string) $this->reason;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
