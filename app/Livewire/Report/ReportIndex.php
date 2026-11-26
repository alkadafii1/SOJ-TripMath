<?php

namespace App\Livewire\Report;

use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReportIndex extends Component
{
    #[Url(except: 'trip-profit')]
    public string $report = 'trip-profit';
    public string $preset = 'month';
    public string $from = '';
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('report.view') && Gate::allows('view-finance'), 403);

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
        abort_unless(Gate::allows('report.view') && Gate::allows('view-finance'), 403);

        $key = array_key_exists($this->report, ReportService::REPORTS) ? $this->report : 'trip-profit';
        [$period, $error] = ReportPeriod::resolve($this->preset, $this->from, $this->to);

        return view('livewire.report.index', [
            'key' => $key,
            'data' => app(ReportService::class)->report($key, $period),
            'period' => $period,
            'error' => $error,
            'presets' => ReportPeriod::PRESETS,
            'canExport' => Gate::allows('report.export'),
        ]);
    }
}
