<?php

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionBillingService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Langganan saya')] class extends Component {
    public string $planId = '';

    public string $retainedBusinessId = '';

    public string $checkoutIdempotencyKey = '';

    public string $checkoutMessage = '';

    public string $checkoutSubscriptionId = '';

    public string $checkoutInvoiceId = '';

    public string $checkoutPaymentId = '';

    public bool $checkoutScheduled = false;

    private function owner(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isOwner() && $user->status === 'active' && $user->hasVerifiedEmail(), 403);

        return $user;
    }

    #[Computed]
    public function plans()
    {
        $this->owner();

        return SubscriptionPlan::query()->where('is_active', true)->orderBy('monthly_price')->get();
    }

    #[Computed]
    public function currentSubscription(): ?Subscription
    {
        $owner = $this->owner();

        return $owner->subscriptions()
            ->with(['plan', 'scheduledPlan', 'retainedBusiness'])
            ->whereIn('status', [
                Subscription::STATUS_PENDING_PAYMENT,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_GRACE_PERIOD,
                Subscription::STATUS_PENDING_REVERIFICATION,
                Subscription::STATUS_SUSPENDED,
            ])
            ->orderByDesc('created_at')
            ->first();
    }

    #[Computed]
    public function businesses()
    {
        return $this->owner()->businesses()
            ->whereNotIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_REJECTED, Business::STATUS_ARCHIVED_BY_DOWNGRADE])
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    #[Computed]
    public function checkoutPayment(): ?Payment
    {
        if ($this->checkoutPaymentId === '') {
            return null;
        }

        return Payment::query()
            ->whereKey((int) $this->checkoutPaymentId)
            ->where('payer_user_id', $this->owner()->getKey())
            ->where('payable_type', Payment::TYPE_SUBSCRIPTION)
            ->first();
    }

    #[Computed]
    public function checkoutInvoice(): ?Invoice
    {
        if ($this->checkoutInvoiceId === '') {
            return null;
        }

        return Invoice::query()
            ->whereKey((int) $this->checkoutInvoiceId)
            ->where('recipient_user_id', $this->owner()->getKey())
            ->first();
    }

    #[Computed]
    public function recentInvoices()
    {
        return $this->owner()->invoices()
            ->whereIn('invoice_type', [Invoice::TYPE_SUBSCRIPTION, Invoice::TYPE_SUBSCRIPTION_UPGRADE])
            ->orderByDesc('issued_at')
            ->limit(10)
            ->get();
    }

    public function checkout(): void
    {
        $owner = $this->owner();

        $data = $this->validate([
            'planId' => ['required', 'integer', 'exists:subscription_plans,id'],
            'retainedBusinessId' => ['nullable', 'integer', 'exists:businesses,id'],
        ], [
            'planId.required' => 'Pilih paket langganan.',
            'planId.exists' => 'Paket tidak ditemukan.',
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail((int) $data['planId']);
        $retainedBusinessId = filled($data['retainedBusinessId']) ? (int) $data['retainedBusinessId'] : null;

        if ($retainedBusinessId !== null) {
            abort_unless($owner->businesses()->whereKey($retainedBusinessId)->exists(), 403);
        }

        $idempotencyKey = $this->checkoutIdempotencyKey !== ''
            ? $this->checkoutIdempotencyKey
            : (string) Str::uuid();

        $this->checkoutIdempotencyKey = $idempotencyKey;

        $result = app(SubscriptionBillingService::class)->checkoutPlan(
            $owner,
            $plan,
            $idempotencyKey,
            $retainedBusinessId,
        );

        $this->checkoutSubscriptionId = (string) $result['subscription']->getKey();
        $this->checkoutInvoiceId = $result['invoice'] ? (string) $result['invoice']->getKey() : '';
        $this->checkoutPaymentId = $result['payment'] ? (string) $result['payment']->getKey() : '';
        $this->checkoutScheduled = (bool) $result['scheduled'];

        if ($result['scheduled']) {
            $this->checkoutMessage = 'Downgrade dijadwalkan untuk periode berikutnya. Paket saat ini tetap berlaku hingga akhir periode.';
        } elseif ($result['payment']) {
            $this->checkoutMessage = 'Invoice langganan dibuat. Selesaikan pembayaran QRIS sebelum batas waktu yang tercantum.';
        } else {
            $this->checkoutMessage = 'Permintaan langganan telah diproses.';
        }

        unset($this->currentSubscription, $this->recentInvoices, $this->checkoutPayment, $this->checkoutInvoice);
    }

    public function startNewCheckout(): void
    {
        $this->reset([
            'planId',
            'retainedBusinessId',
            'checkoutIdempotencyKey',
            'checkoutMessage',
            'checkoutSubscriptionId',
            'checkoutInvoiceId',
            'checkoutPaymentId',
            'checkoutScheduled',
        ]);
        $this->resetValidation();
        unset($this->currentSubscription, $this->recentInvoices, $this->checkoutPayment, $this->checkoutInvoice);
    }
};
?>

<div class="site-container owner-core-page owner-subscription-page py-7 sm:py-9 lg:py-10">
    <header class="owner-core-header">
        <div>
            <p class="owner-core-kicker">Langganan Owner</p>
            <h1>Langganan saya</h1>
            <p>Pilih paket, bayar invoice melalui QRIS, atau jadwalkan downgrade untuk periode berikutnya. Upgrade baru aktif setelah webhook pembayaran terverifikasi.</p>
        </div>
    </header>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if ($checkoutMessage !== '')
        <section class="owner-finance-panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-bold">{{ $checkoutScheduled ? 'Downgrade dijadwalkan' : 'Checkout langganan' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">{{ $checkoutMessage }}</p>
                </div>
                <button type="button" wire:click="startNewCheckout" class="btn btn-secondary shrink-0">Pilih paket lain</button>
            </div>
            @if ($this->checkoutInvoice)
                <div class="mt-4 rounded-xl bg-[var(--surface-soft)] p-4">
                    <p class="text-xs text-[var(--ink-soft)]">Nomor invoice</p>
                    <p class="mt-1 font-bold">{{ $this->checkoutInvoice->invoice_number }}</p>
                    <p class="mt-2 text-2xl font-extrabold">Rp{{ number_format($this->checkoutInvoice->total_amount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-[var(--ink-soft)]">Status invoice: {{ str($this->checkoutInvoice->status)->replace('_', ' ')->title() }}</p>
                </div>
            @endif
            @if ($this->checkoutPayment)
                <div class="mt-4 rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-bold">Pembayaran QRIS</p>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{{ str($this->checkoutPayment->status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <p class="mt-2 text-sm">Nominal: Rp{{ number_format($this->checkoutPayment->amount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-sm text-[var(--ink-soft)]">Batas pembayaran: {{ $this->checkoutPayment->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? 'belum ditentukan' }} WIB</p>
                    @if ($this->checkoutPayment->qris_payload)
                        <label class="ui-label mt-4" for="subscription-qris-payload">Payload QRIS dari gateway</label>
                        <textarea id="subscription-qris-payload" class="ui-input w-full font-mono text-xs" rows="4" readonly>{{ $this->checkoutPayment->qris_payload }}</textarea>
                        <p class="mt-2 text-xs leading-5 text-[var(--ink-soft)]">Payload ditampilkan apa adanya dari adapter pembayaran. Tampilan QR visual akan mengikuti integrasi gateway produksi yang dipilih.</p>
                    @elseif (in_array($this->checkoutPayment->status, [\App\Models\Payment::STATUS_VERIFYING, \App\Models\Payment::STATUS_RECONCILIATION_REQUIRED], true))
                        <p class="mt-3 text-sm text-amber-800">Status gateway perlu diverifikasi. Jangan membuat pembayaran kedua sebelum rekonsiliasi selesai.</p>
                    @endif
                </div>
            @endif
        </section>
    @endif

    @if ($this->currentSubscription)
        <section class="owner-finance-panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm text-[var(--ink-soft)]">Status langganan</p>
                    <h2 class="mt-1 text-2xl font-extrabold">{{ $this->currentSubscription->plan?->name ?? 'Paket tidak tersedia' }}</h2>
                    <p class="mt-2 text-sm">Status: <strong>{{ str($this->currentSubscription->status)->replace('_', ' ')->title() }}</strong></p>
                    <p class="mt-1 text-sm text-[var(--ink-soft)]">Periode: {{ $this->currentSubscription->starts_at?->timezone('Asia/Jakarta')->format('d M Y') }} – {{ $this->currentSubscription->ends_at?->timezone('Asia/Jakarta')->format('d M Y') }} WIB</p>
                    @if ($this->currentSubscription->grace_ends_at && $this->currentSubscription->status === \App\Models\Subscription::STATUS_GRACE_PERIOD)
                        <p class="mt-2 text-sm text-amber-800">Grace period berakhir {{ $this->currentSubscription->grace_ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p>
                    @endif
                    @if ($this->currentSubscription->scheduledPlan)
                        <p class="mt-2 text-sm">Paket berikutnya: <strong>{{ $this->currentSubscription->scheduledPlan->name }}</strong></p>
                        @if ($this->currentSubscription->retainedBusiness)
                            <p class="mt-1 text-sm text-[var(--ink-soft)]">Usaha yang dipertahankan: {{ $this->currentSubscription->retainedBusiness->name }}</p>
                        @endif
                    @endif
                </div>
                @if ($this->currentSubscription->is_trial)
                    <span class="inline-flex w-fit rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-900">Trial</span>
                @endif
            </div>
        </section>
    @endif

    <section class="owner-finance-panel">
        <h2 class="text-xl font-extrabold">Pilih paket</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Paket yang lebih mahal diproses sebagai upgrade prorata. Downgrade Business ke Starter dijadwalkan untuk periode berikutnya.</p>
        <form wire:submit="checkout" class="mt-5 space-y-5">
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($this->plans as $plan)
                    <label wire:key="plan-{{ $plan->id }}" class="owner-plan-option {{ $planId === (string) $plan->id ? 'border-[var(--brand)] bg-[var(--surface-soft)]' : 'border-[var(--border)]' }}">
                        <input type="radio" wire:model="planId" value="{{ $plan->id }}" class="mt-1" required>
                        <span class="min-w-0">
                            <span class="block font-bold">{{ $plan->name }}</span>
                            <span class="mt-1 block text-xl font-extrabold">Rp{{ number_format($plan->monthly_price, 0, ',', '.') }} <span class="text-xs font-medium text-[var(--ink-soft)]">/ {{ $plan->duration_days }} hari</span></span>
                            <span class="mt-2 block text-sm text-[var(--ink-soft)]">Maks. {{ $plan->max_businesses }} usaha · {{ $plan->max_venues_per_business }} venue per usaha</span>
                            <span class="mt-1 block text-sm text-[var(--ink-soft)]">{{ $plan->has_combined_reporting ? 'Laporan gabungan tersedia' : 'Laporan per usaha' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('planId') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            @error('subscription') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            @error('plan') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            @error('retainedBusinessId') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            @if ($this->currentSubscription?->plan?->slug === 'business')
                <div>
                    <label class="ui-label" for="retained-business">Usaha yang dipertahankan jika downgrade ke Starter</label>
                    <select id="retained-business" wire:model="retainedBusinessId" class="ui-input w-full">
                        <option value="">Pilih jika mengajukan downgrade</option>
                        @foreach ($this->businesses as $business)
                            <option value="{{ $business->id }}">{{ $business->name }} — {{ str($business->status)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Lanjutkan checkout</button>
        </form>
    </section>

    <section class="owner-finance-panel">
        <h2 class="text-xl font-extrabold">Riwayat invoice</h2>
        <div class="mt-4 space-y-3">
            @forelse ($this->recentInvoices as $invoice)
                <article wire:key="invoice-{{ $invoice->id }}" class="flex flex-col gap-2 rounded-xl border border-[var(--border)] p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold">{{ $invoice->invoice_number }}</p>
                        <p class="mt-1 text-xs text-[var(--ink-soft)]">{{ $invoice->issued_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · {{ str($invoice->invoice_type)->replace('_', ' ')->title() }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="font-bold">Rp{{ number_format($invoice->total_amount, 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-[var(--ink-soft)]">{{ str($invoice->status)->replace('_', ' ')->title() }}</p>
                    </div>
                </article>
            @empty
                <p class="text-sm text-[var(--ink-soft)]">Belum ada invoice langganan.</p>
            @endforelse
        </div>
    </section>
</div>
