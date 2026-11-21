<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'started_at', 'finished_at', 'duration_seconds', 'pins_count', 'unassigned_count', 'successful', 'error'])]
class PinImport extends Model
{
    public const SOURCE_SPORTEN = 'sportenkalendar';

    public const SOURCE_EVENTIBG = 'eventibg';

    public const SOURCE_KUPIBILETI = 'kupibileti';

    public const LABELS = [
        self::SOURCE_SPORTEN => 'Спортен календар',
        self::SOURCE_EVENTIBG => 'EventiBG',
        self::SOURCE_KUPIBILETI => 'Купи билети',
    ];

    public static function begin(string $source): self
    {
        return self::query()->create([
            'source' => $source,
            'started_at' => now(),
            'successful' => false,
        ]);
    }

    public function sourceLabel(): string
    {
        return self::LABELS[$this->source] ?? $this->source;
    }

    public function durationLabel(): string
    {
        if ($this->finished_at === null) {
            return '—';
        }

        $seconds = (int) $this->duration_seconds;

        if ($seconds < 1) {
            return 'под 1 сек';
        }

        if ($seconds < 60) {
            return $seconds.' сек';
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest === 0 ? $minutes.' мин' : $minutes.' мин '.$rest.' сек';
    }

    /**
     * @param  array{upcoming?: int, current?: int, unassigned?: int}  $counts
     */
    public function complete(array $counts): void
    {
        $this->close($counts, true, null);
    }

    /**
     * @param  array{upcoming?: int, current?: int, unassigned?: int}  $counts
     */
    public function fail(array $counts, string $error): void
    {
        $this->close($counts, false, mb_substr($error, 0, 500));
    }

    /**
     * @param  array{upcoming?: int, current?: int, unassigned?: int}  $counts
     */
    private function close(array $counts, bool $successful, ?string $error): void
    {
        $finished = now();

        $this->forceFill([
            'finished_at' => $finished,
            'duration_seconds' => (int) round(abs($this->started_at->diffInSeconds($finished))),
            'pins_count' => (int) (($counts['upcoming'] ?? 0) + ($counts['current'] ?? 0) + ($counts['unassigned'] ?? 0)),
            'unassigned_count' => (int) ($counts['unassigned'] ?? 0),
            'successful' => $successful,
            'error' => $error,
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_seconds' => 'integer',
            'pins_count' => 'integer',
            'unassigned_count' => 'integer',
            'successful' => 'boolean',
        ];
    }
}
