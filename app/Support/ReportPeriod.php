<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Rentang tanggal untuk dashboard dan laporan. Trip masuk ke periode berdasarkan tanggal selesai (end_date).
 */
final class ReportPeriod
{
    public const PRESETS = [
        'today' => 'Hari ini',
        'week' => 'Minggu ini',
        'month' => 'Bulan ini',
        'year' => 'Tahun ini',
        'custom' => 'Rentang tanggal',
    ];

    public function __construct(
        public readonly string $preset,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {
    }

    /** Membuat periode. Isian kustom yang tidak valid jatuh kembali ke bulan ini. */
    public static function make(string $preset = 'month', ?string $from = null, ?string $to = null, ?Carbon $now = null): self
    {
        $now = ($now ?? now())->copy();

        return match ($preset) {
            'today' => new self('today', $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            'week' => new self('week', $now->copy()->startOfWeek(), $now->copy()->endOfWeek()),
            'year' => new self('year', $now->copy()->startOfYear(), $now->copy()->endOfYear()),
            'custom' => self::custom($from, $to) ?? self::make('month', null, null, $now),
            default => new self('month', $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        };
    }

    /**
     * Seperti make(), tetapi juga mengembalikan pesan kesalahan bila isian kustom tidak valid.
     *
     * @return array{0: self, 1: ?string}
     */
    public static function resolve(string $preset, ?string $from, ?string $to): array
    {
        if ($preset === 'custom' && self::custom($from, $to) === null) {
            return [self::make('month'), 'Isi tanggal mulai dan selesai dengan benar (tanggal mulai tidak boleh setelah tanggal selesai). Sementara ditampilkan bulan ini.'];
        }

        return [self::make($preset, $from, $to), null];
    }

    private static function custom(?string $from, ?string $to): ?self
    {
        if (blank($from) || blank($to)) {
            return null;
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $start->gt($end) ? null : new self('custom', $start, $end);
    }

    public function label(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->format('d/m/Y')
            : $this->from->format('d/m/Y').' - '.$this->to->format('d/m/Y');
    }
}
