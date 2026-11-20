<?php

namespace App\Livewire\Report;

use App\Models\Trip;
use App\Services\ReconciliationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TripFinancialDetail extends Component
{
    public Trip $trip;

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('report.view') && Gate::allows('view-finance'), 403);

        $this->trip = $trip;
    }

    public function render()
    {
        $snapshot = $this->trip->snapshot()->first();
        $service = app(ReconciliationService::class);

        return view('livewire.report.trip-detail', [
            'snapshot' => $snapshot,
            'items' => $snapshot ? $service->items($this->trip) : collect(),
            'figures' => $snapshot ? $service->figures($this->trip) : null,
            'payments' => $this->trip->payments()->orderBy('paid_at')->orderBy('id')->get(),
            'distributions' => $this->trip->distributions()->orderBy('id')->get(),
        ]);
    }
}
