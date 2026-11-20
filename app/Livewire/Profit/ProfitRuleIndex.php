<?php

namespace App\Livewire\Profit;

use App\Services\FinanceCalculator;
use App\Services\ProfitRuleService;
use App\Support\Options;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ProfitRuleIndex extends Component
{
    public array $form = [];

    public ?string $deactivatingSet = null;
    public string $deactivateReason = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('rules.manage'), 403);

        $current = $this->service()->resolve();

        $this->form = [
            'marketing_percent' => $current ? $this->plain($current['marketing']['percent']) : '5',
            'marketing_base' => $current['marketing']['base'] ?? 'actual_profit',
            'owner_percent' => $current ? $this->plain($current['shares']['owner']['percent']) : '60',
            'partner_percent' => $current ? $this->plain($current['shares']['partner']['percent']) : '40',
            'effective_from' => today()->toDateString(),
            'reason' => '',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'min.numeric' => ':attribute minimal :min.',
            'max.numeric' => ':attribute maksimal :max.',
            'in' => ':attribute tidak valid.',
            'date' => ':attribute bukan tanggal yang valid.',
            'after_or_equal' => ':attribute tidak boleh di masa lalu.',
            'min.string' => ':attribute minimal :min karakter.',
            'max.string' => ':attribute terlalu panjang (maksimal :max karakter).',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.marketing_percent' => 'persentase marketing',
            'form.marketing_base' => 'dasar hitung marketing',
            'form.owner_percent' => 'persentase Owner',
            'form.partner_percent' => 'persentase Partner',
            'form.effective_from' => 'tanggal berlaku',
            'form.reason' => 'alasan perubahan',
            'deactivateReason' => 'alasan',
        ];
    }

    protected function service(): ProfitRuleService
    {
        return app(ProfitRuleService::class);
    }

    private function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public function save(): void
    {
        abort_unless(Gate::allows('rules.manage'), 403);

        $data = $this->validate([
            'form.marketing_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.marketing_base' => ['required', 'in:'.Options::keys(Options::PROFIT_BASES)],
            'form.owner_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.partner_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.effective_from' => ['required', 'date', 'after_or_equal:today'],
            'form.reason' => ['required', 'string', 'min:5', 'max:500'],
        ])['form'];

        try {
            $this->service()->createSet([
                'marketing_percent' => (string) $data['marketing_percent'],
                'marketing_base' => $data['marketing_base'],
                'owner_percent' => (string) $data['owner_percent'],
                'partner_percent' => (string) $data['partner_percent'],
                'effective_from' => $data['effective_from'],
            ], $data['reason']);
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->form['reason'] = '';
        session()->flash('status', 'Aturan profit baru disimpan. Trip yang sudah direkonsiliasi tidak berubah.');
    }

    public function startDeactivate(string $ruleSet): void
    {
        $this->deactivatingSet = $ruleSet;
        $this->deactivateReason = '';
        $this->resetErrorBag();
    }

    public function cancelDeactivate(): void
    {
        $this->deactivatingSet = null;
        $this->deactivateReason = '';
        $this->resetErrorBag();
    }

    public function confirmDeactivate(): void
    {
        abort_unless(Gate::allows('rules.manage'), 403);

        $this->validate(['deactivateReason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->service()->deactivateSet((string) $this->deactivatingSet, $this->deactivateReason);
        } catch (\DomainException $e) {
            $this->addError('deactivateReason', $e->getMessage());

            return;
        }

        $this->cancelDeactivate();
        session()->flash('status', 'Paket aturan dinonaktifkan.');
    }

    public function render()
    {
        $finance = app(FinanceCalculator::class);
        $f = $this->form;

        $valid = fn ($v) => is_numeric($v) && (float) $v >= 0 && (float) $v <= 100;
        $sharesOk = $valid($f['owner_percent'] ?? null) && $valid($f['partner_percent'] ?? null);
        $total = $sharesOk ? $finance->sumPercent([$f['owner_percent'], $f['partner_percent']]) : null;

        // Contoh angka dari spesifikasi (Revenue 10.000.000, Actual Cost 6.890.000) dengan isian form saat ini
        $example = null;
        if ($sharesOk && $valid($f['marketing_percent'] ?? null)
            && array_key_exists($f['marketing_base'] ?? '', Options::PROFIT_BASES)
            && $finance->isTotalHundred([$f['owner_percent'], $f['partner_percent']])) {
            $profit = $finance->calculateProfit('10000000', '6890000', 4)['profit'];
            $marketing = $finance->calculateMarketingAllocation('10000000', $profit, $f['marketing_percent'], $f['marketing_base']);
            $distributable = $finance->calculateDistributable($profit, $marketing);
            $shares = $finance->calculateProfitDistribution($distributable, ['owner' => $f['owner_percent'], 'partner' => $f['partner_percent']]);

            $example = [
                'revenue' => '10000000',
                'actual_cost' => '6890000',
                'actual_profit' => $profit,
                'marketing_percent' => $f['marketing_percent'],
                'marketing_base' => $f['marketing_base'],
                'marketing_allocation' => $marketing,
                'distributable' => $distributable,
                'owner_percent' => $shares['owner']['percent'],
                'owner_share' => $shares['owner']['amount'],
                'partner_percent' => $shares['partner']['percent'],
                'partner_share' => $shares['partner']['amount'],
            ];
        }

        return view('livewire.profit.rules', [
            'sets' => $this->service()->sets(),
            'current' => $this->service()->resolve(),
            'total' => $total,
            'totalOk' => $total !== null && $finance->isTotalHundred([$f['owner_percent'], $f['partner_percent']]),
            'example' => $example,
            'bases' => Options::PROFIT_BASES,
        ]);
    }
}
