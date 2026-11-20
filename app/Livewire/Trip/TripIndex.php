<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
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

    public function render()
    {
        $query = Trip::visibleTo(Auth::user());

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
        ]);
    }
}