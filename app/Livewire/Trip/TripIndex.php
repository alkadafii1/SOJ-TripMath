<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
use App\Support\Options;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TripIndex extends Component
{
    use WithPagination;

    public string $search = '';
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $monthFilter = '';

    #[Url(except: '')]
    public string $statuses = '';
    #[Url(except: '')]
    public string $startFrom = '';
    #[Url(except: '')]
    public string $startTo = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('trip.view'), 403);

        // Status diisi dari URL (mis. tautan kartu dashboard). Nilai tak dikenal diabaikan.
        if ($this->statusFilter !== '' && ! array_key_exists($this->statusFilter, Trip::STATUS)) {
            $this->statusFilter = '';
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }
    public function updatingMonthFilter(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'monthFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $query = Trip::visibleTo($user)->with('pricing');

        if ($this->search !== '') {
            $query->where(function ($w) {
                $w->where('trip_code', 'like', '%'.$this->search.'%')
                    ->orWhere('trip_name', 'like', '%'.$this->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$this->search.'%');
            });
        }
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }
        if ($this->typeFilter !== '' && array_key_exists($this->typeFilter, Options::TRIP_TYPES)) {
            $query->where('trip_type', $this->typeFilter);
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->monthFilter)) {
            $month = Carbon::createFromFormat('Y-m-d', $this->monthFilter.'-01')->startOfDay();
            $query->whereDate('start_date', '>=', $month->toDateString())
                ->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString());
        }

        $selected = array_values(array_intersect(explode(',', $this->statuses), array_keys(Trip::STATUS)));
        if ($selected) {
            $query->whereIn('status', $selected);
        }
        foreach (['startFrom' => '>=', 'startTo' => '<='] as $property => $operator) {
            $value = $this->{$property};
            if ($value !== '' && validator(['date' => $value], ['date' => 'date_format:Y-m-d'])->passes()) {
                $query->whereDate('start_date', $operator, $value);
            }
        }

        return view('livewire.trip.index', [
            'trips' => $query->orderByDesc('id')->paginate(10),
            'stats' => $this->stats($user),
            'months' => $this->months($user),
        ]);
    }

    /**
     * Ringkasan di atas tabel. Dihitung dari semua trip yang boleh dilihat
     * pengguna, tanpa terpengaruh pencarian atau filter di bawahnya.
     *
     * - Total: semua trip (termasuk yang dibatalkan)
     * - Sedang Berjalan: status Ongoing
     * - Akan Datang: tanggal mulai hari ini atau lebih, belum berjalan/selesai/batal
     * - Selesai: status Completed atau Reconciled
     */
    protected function stats($user): array
    {
        $byStatus = Trip::visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $upcoming = Trip::visibleTo($user)
            ->whereDate('start_date', '>=', Carbon::today()->toDateString())
            ->whereNotIn('status', ['ongoing', 'completed', 'reconciled', 'cancelled'])
            ->count();

        return [
            'total' => (int) $byStatus->sum(),
            'ongoing' => (int) ($byStatus['ongoing'] ?? 0),
            'upcoming' => $upcoming,
            'finished' => (int) (($byStatus['completed'] ?? 0) + ($byStatus['reconciled'] ?? 0)),
        ];
    }

    /** Bulan (Y-m) yang punya trip, terbaru di atas, untuk pilihan filter bulan. */
    protected function months($user)
    {
        return Trip::visibleTo($user)
            ->distinct()
            ->orderByDesc('start_date')
            ->pluck('start_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))
            ->unique()
            ->values();
    }
}