<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Dashboard utama: apa yang perlu dikerjakan hari ini, posisi trip yang sedang berjalan,
 * lalu ringkasan keuangan historis. Isi dan angka uang menyesuaikan peran pengguna.
 */
class Overview extends Component
{
    public string $preset = 'month';
    public ?string $from = null;
    public ?string $to = null;
    public string $tab = 'ongoing';

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $service = app(DashboardService::class);
        [$period, $error] = \App\Support\ReportPeriod::resolve($this->preset, $this->from, $this->to);
        $actions = $user->can('view-costs')
            ? collect($service->actions($user))->whereIn('key', ['approval', 'actual_cost', 'reconcile'])->values()->all()
            : [];

        return view('livewire.dashboard.workspace', [
            'stats' => $service->operational($user, $period),
            'schedule' => $service->schedule($user, $this->tab === 'ongoing' ? 'ongoing' : 'upcoming'),
            'actions' => $actions,
            'period' => $period,
            'error' => $error,
            'presets' => \App\Support\ReportPeriod::PRESETS,
        ]);
    }
}
