<?php

namespace App\Livewire\Trip;

use App\Models\Trip;
use App\Models\TripPricing as TripPricingModel;
use App\Services\AuditLogger;
use App\Services\PricingCalculator;
use App\Services\TripPricingService;
use App\Support\Options;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PricingPage extends Component
{
    public Trip $trip;

    /** [id item => tarif negosiasi] */
    public array $negotiated = [];
    public string $negotiationNote = '';

    public string $mode = 'dynamic';
    public string $packagePrice = '';

    public array $price = [
        'quoted' => '', 'requested' => '', 'final' => '',
        'payment_terms' => '', 'quote_valid_until' => '', 'terms' => '',
        'below_min_reason' => '', 'change_reason' => '',
    ];

    public string $approvalNote = '';

    public function boot(): void
    {
        abort_unless(auth()->check() && ! auth()->user()->hasRole('partner')
            && Gate::allows('trip.view') && Gate::allows('view-costs'), 403);
    }

    public function hydrate(): void
    {
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($this->trip->id)->exists(), 403);
    }

    public function mount(Trip $trip): void
    {
        abort_unless(Gate::allows('trip.view') && Gate::allows('view-costs'), 403);
        abort_unless(Trip::visibleTo(Auth::user())->whereKey($trip->id)->exists(), 403);

        $this->trip = $trip;
        $this->loadState();
    }

    // ---------------------------------------------------------------- pembantu

    protected function svc(): TripPricingService
    {
        return app(TripPricingService::class);
    }

    protected function calc(): PricingCalculator
    {
        return app(PricingCalculator::class);
    }

    protected function pricingRow(): ?TripPricingModel
    {
        return TripPricingModel::where('trip_id', $this->trip->id)->first();
    }

    protected function plain($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $v = (string) $v;

        return str_contains($v, '.') ? rtrim(rtrim($v, '0'), '.') : $v;
    }

    protected function blankToNull($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    protected function validMoney(string $v): bool
    {
        return preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v) === 1;
    }

    protected function threshold(TripPricingModel $p): ?string
    {
        return $p->min_margin_threshold === null ? null : (string) $p->min_margin_threshold;
    }

    protected function loadState(): void
    {
        $pricing = $this->pricingRow();
        $this->mode = $pricing?->pricing_mode ?? ($this->trip->trip_type === 'open_trip' ? 'per_pax' : 'dynamic');

        $this->negotiated = $this->trip->costItems()->active()
            ->where('is_additional', false)
            ->where('cost_type', '!=', 'percentage')
            ->get()
            ->mapWithKeys(fn ($i) => [$i->id => $this->plain($i->negotiated_cost)])
            ->all();

        if (! $pricing) {
            return;
        }

        $pax = max($this->trip->effectivePax(), 1);
        $conv = function ($v) use ($pricing, $pax) {
            if ($v === null) {
                return '';
            }

            return $this->plain($pricing->pricing_mode === 'per_pax'
                ? ($this->calc()->calculatePricePerPax((string) $v, $pax) ?? '')
                : (string) $v);
        };

        $this->packagePrice = $pricing->pricing_mode === 'package' ? $this->plain($pricing->recommended_price) : '';
        $this->price = [
            'quoted' => $conv($pricing->quoted_price),
            'requested' => $conv($pricing->customer_requested_price),
            'final' => $conv($pricing->final_price),
            'payment_terms' => (string) $pricing->payment_terms,
            'quote_valid_until' => $pricing->quote_valid_until?->format('Y-m-d') ?? '',
            'terms' => (string) $pricing->terms,
            'below_min_reason' => (string) $pricing->below_min_reason,
            'change_reason' => '',
        ];
    }

    // 1. negosiasi vendor

    public function saveNegotiatedCosts(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();

        if (! in_array($this->trip->status, ['draft', 'estimated'], true)) {
            session()->flash('error', 'Biaya negosiasi hanya bisa diubah saat trip Draft atau Estimated. Kembalikan trip ke Estimated dulu.');
            return;
        }

        $this->validate(['negotiated.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,2']]);

        $note = $this->blankToNull($this->negotiationNote);
        $changed = 0;

        DB::transaction(function () use ($note, &$changed) {
            $items = $this->trip->costItems()->active()
                ->where('is_additional', false)
                ->where('cost_type', '!=', 'percentage')
                ->get();

            foreach ($items as $item) {
                $raw = $this->negotiated[$item->id] ?? '';
                $new = ($raw === '' || $raw === null) ? null : $this->calc()->toMoney($raw);
                $old = $item->negotiated_cost;

                $same = ($old === null && $new === null)
                    || ($old !== null && $new !== null && bccomp((string) $old, $new, 2) === 0);
                if ($same) {
                    continue;
                }

                $item->update(['negotiated_cost' => $new]);
                AuditLogger::record('trip_cost_item', $item->id, 'negotiated',
                    ['negotiated_cost' => $old], ['negotiated_cost' => $new], $note);
                $changed++;
            }
        });

        $this->negotiationNote = '';
        session()->flash('status', $changed ? "{$changed} biaya negosiasi disimpan." : 'Tidak ada perubahan.');
    }

    // 2. mulai negosiasi harga

    public function startNegotiation(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();

                if (! in_array($this->trip->status, ['draft', 'estimated'], true)) {
            session()->flash('error', 'Negosiasi customer hanya bisa dimulai saat estimasi masih dapat direvisi.');
            return;
        }
        if ($this->trip->status === 'draft') {
            abort_unless(Gate::allows('trip.update'), 403);
        }
        if (! $this->trip->costItems()->active()->where('is_additional', false)->exists()) {
            session()->flash('error', 'Tambahkan minimal satu komponen biaya sebelum melanjutkan ke negosiasi customer.');
            return;
        }
        if ($this->trip->effectivePax() < 1) {
            session()->flash('error', 'Jumlah peserta harus lebih dari 0 sebelum melanjutkan ke negosiasi customer.');
            return;
        }
        if (! array_key_exists($this->mode, Options::PRICING_MODES)) {
            session()->flash('error', 'Mode harga tidak dikenal.');
            return;
        }
        if ($this->mode === 'per_pax' && $this->trip->trip_type !== 'open_trip') {
            session()->flash('error', 'Mode per pax hanya untuk Open Trip.');
            return;
        }

        $rule = $this->svc()->activeRule();
        if (! $rule) {
            session()->flash('error', 'Belum ada aturan harga aktif. Minta Owner membuatnya di menu Aturan Harga.');
            return;
        }

        if ($this->mode === 'package') {
            $this->validate(['packagePrice' => ['required', 'numeric', 'gt:0', 'decimal:0,2']]);
        }

                DB::transaction(function () use ($rule) {
            $this->trip = Trip::whereKey($this->trip->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($this->trip->status, ['draft', 'estimated'], true), 409);

            // Simpan isian vendor sebelum menghitung harga, dalam transaksi yang sama.
            $this->saveNegotiatedCosts();
            session()->forget('status');
            $rec = $this->svc()->recommend($this->trip, $rule->method, (string) $rule->rate_percent, $rule->rounding_rule, $this->mode);
            $recommended = $this->mode === 'package'
                ? $this->calc()->toMoney($this->packagePrice)
                : $rec['recommended'];
            if (bccomp($recommended, '0', 2) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'negotiationReady' => 'Harga rekomendasi harus lebih dari Rp0. Lengkapi tarif biaya atau harga paket terlebih dahulu.',
                ]);
            }

            if ($this->trip->status === 'draft') {
                abort_unless(Gate::allows('trip.update'), 403);
                $this->trip->update(['status' => 'estimated']);
                AuditLogger::record('trip', $this->trip->id, 'status_changed', ['status' => 'draft'], ['status' => 'estimated']);
            }
            $existing = $this->pricingRow();

            $data = [
                'pricing_rule_id' => $rule->id,
                'pricing_mode' => $this->mode,
                'pricing_method' => $rule->method,
                'rate_percent' => $rule->rate_percent,
                'rounding_rule' => $rule->rounding_rule,
                'min_margin_threshold' => $rule->min_margin_threshold,
                'block_below_min' => $rule->block_below_min,
                'require_approval' => $rule->require_approval,
                'recommended_price' => $recommended,
                'approval_status' => 'none',
                'approved_by' => null,
                'approved_at' => null,
            ];

            if ($existing) {
                $oldRecommended = $existing->recommended_price;
                if ($this->mode !== 'per_pax') {
                    $data['price_per_pax'] = null;
                }
                if ($existing->final_price !== null) {
                    $data['customer_discount'] = $this->calc()->calculateCustomerDiscount(
                        $recommended, (string) $existing->final_price
                    );
                }
                $existing->update($data);

                AuditLogger::record('trip_pricing', $existing->id, 'recommended_recalculated',
                    ['recommended_price' => $oldRecommended], ['recommended_price' => $recommended]);
            } else {
                $pricing = TripPricingModel::create($data + ['trip_id' => $this->trip->id]);

                AuditLogger::record('trip_pricing', $pricing->id, 'created', null, [
                    'recommended_price' => $recommended,
                    'pricing_mode' => $this->mode,
                    'pricing_rule_id' => $rule->id,
                ]);
            }

            $this->trip->update(['status' => 'negotiation']);
            AuditLogger::record('trip', $this->trip->id, 'status_changed', ['status' => 'estimated'], ['status' => 'negotiation']);
        });

        $this->loadState();
        session()->flash('status', 'Negosiasi customer dimulai. Biaya vendor dan harga rekomendasi tersimpan. Isi penawaran dan kesepakatan di bawah.');
    }

    // 3. negosiasi dengan customer

    public function saveNegotiation(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();
        $pricing = $this->pricingRow();

        if ($this->trip->status !== 'negotiation' || ! $pricing) {
            session()->flash('error', 'Harga hanya bisa diubah saat trip berstatus Negotiation.');
            return;
        }

        $this->validate([
            'price.quoted' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price.requested' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'price.final' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'price.payment_terms' => ['nullable', 'string', 'max:1000'],
            'price.quote_valid_until' => ['nullable', 'date_format:Y-m-d'],
            'price.terms' => ['nullable', 'string', 'max:2000'],
            'price.below_min_reason' => ['nullable', 'string', 'max:1000'],
            'price.change_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $pax = $this->trip->effectivePax();
        $perPax = $pricing->pricing_mode === 'per_pax';

        // Pada mode per pax, isian adalah harga per orang; disimpan sebagai total
        $total = function ($v) use ($perPax, $pax) {
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }

            return $this->calc()->calculateTotalFromPerPax($v, $pax, $perPax);
        };

        $quoted = $total($this->price['quoted'] ?? '');
        $requested = $total($this->price['requested'] ?? '');
        $final = $total($this->price['final'] ?? '');

        $oldFinal = $pricing->final_price;
        $finalChanged = ($oldFinal === null) !== ($final === null)
            || ($oldFinal !== null && $final !== null && bccomp((string) $oldFinal, $final, 2) !== 0);

        $reason = trim((string) ($this->price['change_reason'] ?? ''));
        if ($finalChanged && $oldFinal !== null && $reason === '') {
            $this->addError('price.change_reason', 'Alasan perubahan harga final wajib diisi.');
            return;
        }

        $pricing->fill([
            'quoted_price' => $quoted,
            'customer_requested_price' => $requested,
            'final_price' => $final,
            'price_per_pax' => ($perPax && $final !== null) ? $this->calc()->toMoney($this->price['final']) : null,
            'customer_discount' => $final !== null
                ? $this->calc()->calculateCustomerDiscount((string) $pricing->recommended_price, $final)
                : null,
            'payment_terms' => $this->blankToNull($this->price['payment_terms'] ?? ''),
            'quote_valid_until' => $this->blankToNull($this->price['quote_valid_until'] ?? ''),
            'terms' => $this->blankToNull($this->price['terms'] ?? ''),
            'below_min_reason' => $this->blankToNull($this->price['below_min_reason'] ?? ''),
        ]);

        // Harga final berubah: persetujuan lama tidak berlaku lagi
        if ($finalChanged && $pricing->approval_status !== 'none') {
            $pricing->approval_status = 'none';
            $pricing->approved_by = null;
            $pricing->approved_at = null;
        }

        $dirty = $pricing->getDirty();
        if (! $dirty) {
            session()->flash('status', 'Tidak ada perubahan.');
            return;
        }

        $old = [];
        foreach (array_keys($dirty) as $key) {
            $old[$key] = $pricing->getRawOriginal($key);
        }

        DB::transaction(function () use ($pricing, $old, $dirty, $finalChanged, $reason) {
            $pricing->save();
            AuditLogger::record('trip_pricing', $pricing->id, 'updated', $old, $dirty,
                ($finalChanged && $reason !== '') ? $reason : null);
        });

        $this->price['change_reason'] = '';
        session()->flash('status', 'Harga disimpan.');
    }

    // 4. persetujuan

    /** Syarat umum untuk mengajukan atau memberi persetujuan. */
    protected function approvalProblem(?TripPricingModel $p): ?string
    {
        if ($this->trip->status !== 'negotiation' || ! $p) {
            return 'Persetujuan hanya untuk trip berstatus Negotiation.';
        }
        if ($p->final_price === null) {
            return 'Simpan harga final dulu.';
        }

        $eval = $this->svc()->evaluate($this->trip, (string) $p->final_price, $this->threshold($p));
        if (! $eval['below_min']) {
            return 'Harga final tidak di bawah batas minimum, persetujuan tidak diperlukan.';
        }
        if ($p->block_below_min) {
            return 'Aturan harga memblokir harga di bawah batas minimum. Naikkan harga final.';
        }
        if (! $p->require_approval) {
            return 'Aturan harga tidak memakai persetujuan; cukup isi alasan.';
        }

        return null;
    }

    public function requestApproval(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();
        $pricing = $this->pricingRow();

        if ($problem = $this->approvalProblem($pricing)) {
            session()->flash('error', $problem);
            return;
        }
        if ($pricing->approval_status === 'pending') {
            session()->flash('error', 'Permintaan persetujuan sudah diajukan.');
            return;
        }
        if ($pricing->approval_status === 'approved') {
            session()->flash('error', 'Harga ini sudah disetujui.');
            return;
        }
        if (trim((string) $pricing->below_min_reason) === '') {
            session()->flash('error', 'Isi alasan harga di bawah batas, klik Simpan, lalu ajukan persetujuan.');
            return;
        }

        $old = $pricing->approval_status;
        DB::transaction(function () use ($pricing, $old) {
            $pricing->update(['approval_status' => 'pending']);
            AuditLogger::record('trip_pricing', $pricing->id, 'approval_requested',
                ['approval_status' => $old], ['approval_status' => 'pending'], $pricing->below_min_reason);
        });

        session()->flash('status', 'Permintaan persetujuan dikirim ke Owner.');
    }

    public function approve(): void
    {
        abort_unless(Gate::allows('pricing.approve'), 403);
        $this->trip->refresh();
        $pricing = $this->pricingRow();

        if ($problem = $this->approvalProblem($pricing)) {
            session()->flash('error', $problem);
            return;
        }
        if ($pricing->approval_status === 'approved') {
            session()->flash('error', 'Harga ini sudah disetujui.');
            return;
        }

        $this->validate(['approvalNote' => ['required', 'string', 'min:3', 'max:500']]);

        $old = $pricing->approval_status;
        DB::transaction(function () use ($pricing, $old) {
            $pricing->update([
                'approval_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
            AuditLogger::record('trip_pricing', $pricing->id, 'approved',
                ['approval_status' => $old], ['approval_status' => 'approved'], $this->approvalNote);
        });

        $this->approvalNote = '';
        session()->flash('status', 'Harga disetujui.');
    }

    public function reject(): void
    {
        abort_unless(Gate::allows('pricing.approve'), 403);
        $this->trip->refresh();
        $pricing = $this->pricingRow();

        if ($this->trip->status !== 'negotiation' || ! $pricing || $pricing->approval_status !== 'pending') {
            session()->flash('error', 'Tidak ada permintaan persetujuan yang menunggu.');
            return;
        }

        $this->validate(['approvalNote' => ['required', 'string', 'min:3', 'max:500']]);

        DB::transaction(function () use ($pricing) {
            $pricing->update(['approval_status' => 'rejected', 'approved_by' => null, 'approved_at' => null]);
            AuditLogger::record('trip_pricing', $pricing->id, 'rejected',
                ['approval_status' => 'pending'], ['approval_status' => 'rejected'], $this->approvalNote);
        });

        $this->approvalNote = '';
        session()->flash('status', 'Permintaan persetujuan ditolak.');
    }

    // 5. status trip

    public function confirmPrice(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();
        $pricing = $this->pricingRow();

        if ($this->trip->status !== 'negotiation' || ! $pricing) {
            session()->flash('error', 'Trip harus berstatus Negotiation.');
            return;
        }
        if ($problem = $this->svc()->confirmationProblem($this->trip, $pricing)) {
            session()->flash('error', $problem);
            return;
        }

        DB::transaction(function () use ($pricing) {
            $this->trip->update(['status' => 'confirmed']);
            AuditLogger::record('trip', $this->trip->id, 'status_changed',
                ['status' => 'negotiation'],
                ['status' => 'confirmed', 'final_price' => (string) $pricing->final_price]);
        });

        session()->flash('status', 'Harga final dikonfirmasi. Trip berstatus Confirmed.');
    }

    public function backToEstimated(): void
    {
        abort_unless(Gate::allows('pricing.negotiate'), 403);
        $this->trip->refresh();

        if ($this->trip->status !== 'negotiation') {
            session()->flash('error', 'Hanya trip berstatus Negotiation yang bisa dikembalikan.');
            return;
        }

        DB::transaction(function () {
            $this->trip->update(['status' => 'estimated']);

            if ($pricing = $this->pricingRow()) {
                $pricing->update(['approval_status' => 'none', 'approved_by' => null, 'approved_at' => null]);
            }
            AuditLogger::record('trip', $this->trip->id, 'status_changed', ['status' => 'negotiation'], ['status' => 'estimated']);
        });

        session()->flash('status', 'Estimasi dibuka untuk revisi di halaman ini. Sesuaikan biaya vendor, lalu klik Lanjut ke Negosiasi Customer untuk menghitung ulang harga rekomendasi.');
    }

    // tampilan

    public function render()
    {
        $svc = $this->svc();
        $trip = $this->trip;
        $pricing = $this->pricingRow();
        $pax = $trip->effectivePax();
        $canNegotiate = Gate::allows('pricing.negotiate');

        $preview = null;
        if (in_array($trip->status, ['draft', 'estimated'], true) && ($rule = $svc->activeRule())) {
            $rec = $svc->recommend($trip, $rule->method, (string) $rule->rate_percent, $rule->rounding_rule, $this->mode);
            $recommended = $this->mode === 'package'
                ? ($this->validMoney($this->packagePrice) ? $this->calc()->toMoney($this->packagePrice) : null)
                : $rec['recommended'];
            $threshold = $rule->min_margin_threshold === null ? null : (string) $rule->min_margin_threshold;

            $preview = [
                'rule' => $rule,
                'rec' => $rec,
                'recommended' => $recommended,
                'eval' => ($recommended !== null && bccomp($recommended, '0', 2) > 0)
                    ? $svc->evaluate($trip, $recommended, $threshold)
                    : null,
            ];
        }

        $evalFinal = $evalSaved = $problem = null;
        if ($pricing) {
            $threshold = $this->threshold($pricing);
            $perPax = $pricing->pricing_mode === 'per_pax';

            $typed = trim((string) ($this->price['final'] ?? ''));
            $previewFinal = $this->validMoney($typed)
                ? $this->calc()->calculateTotalFromPerPax($typed, $pax, $perPax)
                : ($pricing->final_price !== null ? (string) $pricing->final_price : null);

            if ($previewFinal !== null && bccomp($previewFinal, '0', 2) > 0) {
                $evalFinal = $svc->evaluate($trip, $previewFinal, $threshold, (string) $pricing->recommended_price);
            }
            if ($pricing->final_price !== null) {
                $evalSaved = $svc->evaluate($trip, (string) $pricing->final_price, $threshold);
            }
            $problem = $svc->confirmationProblem($trip, $pricing);
        }

        return view('livewire.trip.pricing', [
            'pricing' => $pricing,
            'costs' => $svc->costTotals($trip),
            'items' => $trip->costItems()->active()->where('is_additional', false)->orderBy('category')->orderBy('id')->get(),
            'pax' => $pax,
            'costEditable' => $canNegotiate && in_array($trip->status, ['draft', 'estimated'], true),
            'priceEditable' => $canNegotiate && $trip->status === 'negotiation' && $pricing !== null,
            'canNegotiate' => $canNegotiate,
            'zeroCosts' => $trip->costItems()->active()
                ->where('is_additional', false)
                ->where('cost_type', '!=', 'percentage')
                ->where('standard_cost', '<=', 0)
                ->where(fn ($q) => $q->whereNull('negotiated_cost')->orWhere('negotiated_cost', '<=', 0))
                ->pluck('item_name')
                ->all(),
            'canApprove' => Gate::allows('pricing.approve'),
            'showProfit' => Gate::allows('view-finance'),
            'preview' => $preview,
            'evalFinal' => $evalFinal,
            'evalSaved' => $evalSaved,
            'problem' => $problem,
        ]);
    }
}