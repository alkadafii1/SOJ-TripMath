<?php

namespace App\Livewire\Dashboard;

use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Summary extends Component
{
    public string $preset = 'month';
    public string $from = '';
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('view-finance'), 403);

        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function updatedPreset(string $value): void
    {
        if (! array_key_exists($value, ReportPeriod::PRESETS)) {
            $this->preset = 'month';
        }
    }

    public function render()
    {
        abort_unless(Gate::allows('view-finance'), 403);

        [$period, $error] = ReportPeriod::resolve($this->preset, $this->from, $this->to);
        $service = app(ReportService::class);

        return view('livewire.dashboard.summary', [
            'period' => $period,
            'error' => $error,
            's' => $service->summary($period),
            'statusCounts' => $service->statusCounts($period),
            'presets' => ReportPeriod::PRESETS,
            'canReport' => Gate::allows('report.view'),
        ]);
    }
}
