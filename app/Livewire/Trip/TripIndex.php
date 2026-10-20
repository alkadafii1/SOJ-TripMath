<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class TripIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('trip.view'), 403);
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

        return view('livewire.trip.index', [
            'trips' => $query->orderByDesc('id')->paginate(10),
        ]);
    }
}