<?php

namespace App\Livewire\Pricing;

use App\Livewire\Master\MasterCrud;
use App\Models\PricingRule;
use App\Models\TripPricing;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

class PricingRuleIndex extends MasterCrud
{
    protected function modelClass(): string { return PricingRule::class; }
    protected function entity(): string { return 'pricing_rule'; }
    protected function heading(): string { return 'Aturan Harga'; }
    protected function searchFields(): array { return ['method']; }
    protected function rateFields(): array { return ['rate_percent', 'min_margin_threshold']; }
    protected function viewAbility(): string { return 'rules.manage'; }
    protected function manageAbility(): string { return 'rules.manage'; }
    protected function tabs(): array { return []; }

    protected function fields(): array
    {
        return [
            $this->f('method', 'Metode harga', 'select',
                ['required', 'in:'.Options::keys(Options::PRICING_METHODS)],
                ['options' => Options::PRICING_METHODS, 'default' => 'cost_plus_margin',
                 'help' => 'Margin dan markup berbeda: margin dihitung dari harga jual, markup dari biaya.']),
            $this->f('rate_percent', 'Target margin / markup (%)', 'percent', [
                'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    if (($this->form['method'] ?? null) === 'cost_plus_margin' && (float) $value >= 100) {
                        $fail('Target margin harus di bawah 100%.');
                    }
                },
            ]),
            $this->f('rounding_rule', 'Pembulatan harga jual', 'select',
                ['required', 'in:'.Options::keys(Options::ROUNDING_RULES)],
                ['options' => Options::ROUNDING_RULES, 'default' => 'exact']),
            $this->f('min_margin_threshold', 'Batas margin minimum (%)', 'percent',
                ['nullable', 'numeric', 'min:0', 'max:99.99'],
                ['help' => 'Kosongkan bila tidak ada batas.']),
            $this->f('block_below_min', 'Blokir bila di bawah batas', 'checkbox', ['boolean'],
                ['help' => 'Trip tidak bisa dikonfirmasi sama sekali.']),
            $this->f('require_approval', 'Butuh persetujuan Owner bila di bawah batas', 'checkbox', ['boolean']),
            $this->f('effective_from', 'Berlaku dari', 'date', ['nullable', 'date']),
            $this->f('effective_to', 'Berlaku sampai', 'date', ['nullable', 'date', 'after_or_equal:form.effective_from']),
            $this->statusField(),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'method_label', 'label' => 'Metode'],
            ['key' => 'rate_percent', 'label' => 'Margin/Markup', 'type' => 'percent'],
            ['key' => 'rounding_label', 'label' => 'Pembulatan'],
            ['key' => 'min_margin_threshold', 'label' => 'Batas minimum', 'type' => 'percent'],
            ['key' => 'block_below_min', 'label' => 'Blokir', 'type' => 'bool'],
            ['key' => 'require_approval', 'label' => 'Approval', 'type' => 'bool'],
            ['key' => 'effective_from', 'label' => 'Berlaku dari', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ];
    }

    protected function isInUse(Model $model): bool
    {
        return TripPricing::where('pricing_rule_id', $model->id)->exists();
    }

    protected function notice(): ?string
    {
    $active = PricingRule::effectiveOn(now()->toDateString())
        ->orderByDesc('effective_from')
        ->orderByDesc('id')
        ->get();

    if ($active->count() < 2) {
        return null;
    }

    $used = $active->first();

    return 'Ada '.$active->count().' aturan harga yang aktif bersamaan. Sistem memakai yang paling baru ('
        .$used->method_label.' '.number_format((float) $used->rate_percent, 2, ',', '.').'%). '
        .'Nonaktifkan aturan yang tidak dipakai agar tidak membingungkan.';
    }
}