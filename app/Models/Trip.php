<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    public const STATUS = [
        'draft' => 'Draft',
        'estimated' => 'Estimated',
        'negotiation' => 'Negotiation',
        'confirmed' => 'Confirmed',
        'deposit_paid' => 'Deposit Paid',
        'paid' => 'Paid',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'reconciled' => 'Reconciled',
        'cancelled' => 'Cancelled',
    ];

    /** Status yang masih boleh diubah isinya. */
    public const EDITABLE = ['draft', 'estimated'];

    /** Mesin status lengkap sesuai spesifikasi. Sprint 4 baru memakai sebagian. */
    public const TRANSITIONS = [
        'draft' => ['estimated', 'cancelled'],
        'estimated' => ['draft', 'negotiation', 'cancelled'],
        'negotiation' => ['estimated', 'confirmed', 'cancelled'],
        'confirmed' => ['deposit_paid', 'paid', 'cancelled'],
        'deposit_paid' => ['paid', 'ongoing', 'cancelled'],
        'paid' => ['ongoing', 'cancelled'],
        'ongoing' => ['completed', 'cancelled'],
        'completed' => ['reconciled'],
        'reconciled' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'trip_code', 'trip_name', 'customer_name', 'customer_contact', 'trip_type',
        'package_version_id', 'start_date', 'end_date', 'duration_days', 'nights', 'pax',
        'min_pax', 'max_capacity', 'confirmed_pax', 'status',
        'itinerary', 'inclusions', 'exclusions', 'notes', 'cancel_reason',
        'created_by', 'assigned_staff_id', 'assigned_partner_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'integer',
            'nights' => 'integer',
            'pax' => 'integer',
            'min_pax' => 'integer',
            'max_capacity' => 'integer',
            'confirmed_pax' => 'integer',
        ];
    }

    public static function daysBetween(string $start, string $end): int
    {
        return (int) round(Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay())) + 1;
    }

    public function getNightsAttribute(): int
    {
        $stored = $this->attributes['nights'] ?? null;

        return $stored !== null ? (int) $stored : max($this->duration_days - 1, 0);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /**
     * Pax yang dipakai untuk menghitung biaya dan harga.
     * Open Trip yang sudah punya confirmed pax memakai confirmed pax;
     * selain itu memakai pax rencana (skenario).
     */
    public function effectivePax(): int
    {
        return ($this->trip_type === 'open_trip' && $this->confirmed_pax)
            ? (int) $this->confirmed_pax
            : (int) $this->pax;
    }

    public function pricing()
    {
        return $this->hasOne(TripPricing::class);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-yellow-100 text-yellow-800',
            'estimated', 'negotiation' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'reconciled' => 'bg-green-100 text-green-800',
            default => 'bg-indigo-100 text-indigo-800',
        };
    }

    /** Partner hanya melihat trip yang ditugaskan kepadanya. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('partner')) {
            return $query->where('assigned_partner_id', $user->id);
        }

        return $query;
    }

    public function packageVersion() { return $this->belongsTo(PackageVersion::class); }
    public function costItems() { return $this->hasMany(TripCostItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function staff() { return $this->belongsTo(User::class, 'assigned_staff_id'); }
    public function partner() { return $this->belongsTo(User::class, 'assigned_partner_id'); }

    public function destinations()
    {
        return $this->belongsToMany(Destination::class, 'trip_destinations')
            ->withPivot('sort_order')
            ->orderBy('trip_destinations.sort_order');
    }
}