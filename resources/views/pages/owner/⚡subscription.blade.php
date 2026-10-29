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

        $current = $this->currentSubscription;
        $canChangePlan = $current && in_array($current->status, [
            Subscription::STATUS_TRIALING,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_GRACE_PERIOD,
        ], true);

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->when($canChangePlan, fn ($query) => $query->whereKeyNot($current->plan_id))
            ->orderBy('monthly_price')
            ->get();
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
        $owner = $this->owner();

        if ($this->checkoutPaymentId !== '') {
            return Payment::query()
                ->whereKey((int) $this->checkoutPaymentId)
                ->where('payer_user_id', $owner->getKey())
                ->where('payable_type', Payment::TYPE_SUBSCRIPTION)
                ->first();
        }

        $current = $this->currentSubscription;

        if (! $current) {
            return null;
        }

        return Payment::query()
            ->where('payer_user_id', $owner->getKey())
            ->where('payable_type', Payment::TYPE_SUBSCRIPTION)
            ->where('payable_id', $current->getKey())
            ->whereIn('status', [
                Payment::STATUS_PENDING,
                Payment::STATUS_VERIFYING,
                Payment::STATUS_RECONCILIATION_REQUIRED,
            ])
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function checkoutInvoice(): ?Invoice
    {
        $owner = $this->owner();

        if ($this->checkoutInvoiceId !== '') {
            return Invoice::query()
                ->whereKey((int) $this->checkoutInvoiceId)
                ->where('recipient_user_id', $owner->getKey())
                ->first();
        }

        $payment = $this->checkoutPayment;

        if (! $payment) {
            return null;
        }

        return Invoice::query()
            ->where('reference_id', $payment->payable_id)
            ->where('recipient_user_id', $owner->getKey())
            ->whereJsonContains('line_items_json->payment_idempotency_key', $payment->idempotency_key)
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

    public function renewCurrentPlan(): void
    {
        $current = $this->currentSubscription;

        abort_unless($current && in_array($current->status, [
            Subscription::STATUS_TRIALING,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_GRACE_PERIOD,
        ], true), 403);

        $this->planId = (string) $current->plan_id;
        $this->retainedBusinessId = '';
        $this->checkoutIdempotencyKey = '';
        $this->checkout();
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
    @php
        $current = $this->currentSubscription;
        $checkoutPayment = $this->checkoutPayment;
        $checkoutInvoice = $this->checkoutInvoice;
        $hasOpenPayment = $checkoutPayment && in_array($checkoutPayment->status, [
            \App\Models\Payment::STATUS_PENDING,
            \App\Models\Payment::STATUS_VERIFYING,
            \App\Models\Payment::STATUS_RECONCILIATION_REQUIRED,
        ], true);
        $showCheckout = $checkoutMessage !== '' || $hasOpenPayment;
        $isMockPayment = $checkoutPayment && str_starts_with((string) ($checkoutPayment->qris_payload ?? ''), 'MOCK-QRIS:');
        $isSubscribed = $current && in_array($current->status, [
            \App\Models\Subscription::STATUS_TRIALING,
            \App\Models\Subscription::STATUS_ACTIVE,
            \App\Models\Subscription::STATUS_GRACE_PERIOD,
        ], true);
        $isStarter = $isSubscribed && $current->plan?->slug === 'starter';
        $isUpgradeInvoice = $checkoutInvoice?->invoice_type === \App\Models\Invoice::TYPE_SUBSCRIPTION_UPGRADE;
        $subscriptionLabel = match ($current?->status) {
            'active' => 'Aktif',
            'trialing' => 'Masa percobaan',
            'pending_payment' => 'Menunggu pembayaran',
            'grace_period' => 'Masa tenggang',
            'suspended' => 'Ditangguhkan',
            'pending_reverification' => 'Menunggu verifikasi ulang',
            'cancelled' => 'Dibatalkan',
            default => 'Belum aktif',
        };
        $paymentLabel = match ($checkoutPayment?->status) {
            'pending' => 'Menunggu pembayaran',
            'verifying' => 'Sedang diverifikasi',
            'reconciliation_required' => 'Perlu pemeriksaan',
            'paid' => 'Lunas',
            'failed' => 'Gagal',
            'expired' => 'Kedaluwarsa',
            default => 'Belum ada pembayaran',
        };
    @endphp

    <header class="owner-core-header owner-subscription-header">
        <div>
            <h1>Langganan saya</h1>
            <p>Informasi paket, pembayaran, dan perubahan langganan usaha Anda.</p>
        </div>
    </header>

    @if (session()->has('status'))
        <p role="status" class="owner-subscription-notice is-success">{{ session('status') }}</p>
    @endif

    @if ($current)
        <section class="owner-finance-panel owner-subscription-overview" aria-labelledby="subscription-current-heading">
            <div class="owner-subscription-overview-head">
                <div>
                    <p class="owner-subscription-section-label">{{ $current->status === 'pending_payment' ? 'Paket yang dipilih' : 'Paket saat ini' }}</p>
                    <h2 id="subscription-current-heading">{{ $current->plan?->name ?? 'Paket tidak tersedia' }}</h2>
                </div>
                <span class="owner-subscription-status {{ in_array($current->status, ['active', 'trialing'], true) ? 'is-active' : 'is-attention' }}">
                    {{ $subscriptionLabel }}
                </span>
            </div>

            <dl class="owner-subscription-facts">
                <div>
                    <dt>Masa berlaku hingga</dt>
                    <dd>{{ $current->ends_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Belum aktif' }}</dd>
                </div>
                <div>
                    <dt>Batas usaha</dt>
                    <dd>{{ $current->plan?->max_businesses ?? '—' }} usaha</dd>
                </div>
                <div>
                    <dt>Batas venue</dt>
                    <dd>{{ $current->plan?->max_venues_per_business ?? '—' }} per usaha</dd>
                </div>
            </dl>

            @if ($current->status === \App\Models\Subscription::STATUS_GRACE_PERIOD && $current->grace_ends_at)
                <p class="owner-subscription-notice is-attention">Masa tenggang berakhir {{ $current->grace_ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. Perpanjang langganan agar layanan tidak ditangguhkan.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_SUSPENDED)
                <p class="owner-subscription-notice is-attention">Langganan ditangguhkan. Selesaikan pembayaran untuk memulai proses pemulihan usaha.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_PENDING_REVERIFICATION)
                <p class="owner-subscription-notice is-attention">Pembayaran telah diproses. Usaha menunggu persetujuan verifikasi ulang dari Super Admin.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_PENDING_PAYMENT)
                <p class="owner-subscription-notice">Paket baru belum aktif. Selesaikan pembayaran tagihan terlebih dahulu.</p>
            @endif

            @if ($current->scheduledPlan)
                <p class="owner-subscription-notice">
                    Perubahan berikutnya: <strong>{{ $current->scheduledPlan->name }}</strong> pada periode selanjutnya.
                    @if ($current->retainedBusiness)
                        Usaha yang dipertahankan: <strong>{{ $current->retainedBusiness->name }}</strong>.
                    @endif
                </p>
            @endif
        </section>
    @else
        <section class="owner-finance-panel owner-subscription-overview" aria-labelledby="subscription-current-heading">
            <p class="owner-subscription-section-label">Paket saat ini</p>
            <h2 id="subscription-current-heading">Belum berlangganan</h2>
            <p class="owner-subscription-intro">Pilih paket di bawah agar Anda dapat mulai mengelola usaha dan venue.</p>
        </section>
    @endif

    @if ($showCheckout)
        <section class="owner-finance-panel owner-subscription-checkout" id="subscription-checkout" aria-labelledby="subscription-checkout-heading">
            <div class="owner-subscription-checkout-head">
                <div>
                    <h2 id="subscription-checkout-heading">
                        {{ $checkoutScheduled ? 'Perubahan paket dijadwalkan' : ($isUpgradeInvoice ? 'Upgrade menunggu pembayaran' : 'Tagihan langganan') }}
                    </h2>
                    <p>
                        @if ($checkoutScheduled)
                            {{ $checkoutMessage }}
                        @elseif ($hasOpenPayment)
                            {{ $isUpgradeInvoice ? 'Paket saat ini tetap berlaku sampai pembayaran upgrade berhasil diverifikasi.' : 'Selesaikan tagihan sebelum batas waktu agar langganan dapat diproses.' }}
                        @else
                            {{ $checkoutMessage }}
                        @endif
                    </p>
                </div>
                @unless ($hasOpenPayment)
                    <button type="button" wire:click="startNewCheckout" class="btn btn-secondary">Kembali ke pilihan paket</button>
                @endunless
            </div>

            @if ($checkoutInvoice)
                <div class="owner-subscription-invoice">
                    <div class="owner-subscription-invoice-total">
                        <span>Total tagihan {{ $isUpgradeInvoice ? 'upgrade' : 'langganan' }}</span>
                        <strong>Rp{{ number_format($checkoutInvoice->total_amount, 0, ',', '.') }}</strong>
                    </div>
                    <dl>
                        <div>
                            <dt>Nomor invoice</dt>
                            <dd>{{ $checkoutInvoice->invoice_number }}</dd>
                        </div>
                        <div>
                            <dt>Status invoice</dt>
                            <dd>{{ $checkoutInvoice->status === 'paid' ? 'Lunas' : ($checkoutInvoice->status === 'unpaid' ? 'Belum dibayar' : str($checkoutInvoice->status)->replace('_', ' ')->title()) }}</dd>
                        </div>
                        @if ($checkoutPayment)
                            <div>
                                <dt>Batas pembayaran</dt>
                                <dd>{{ $checkoutPayment->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Belum ditentukan' }} WIB</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($checkoutPayment)
                <div class="owner-subscription-payment">
                    <div class="owner-subscription-payment-head">
                        <h3>Status pembayaran</h3>
                        <span class="owner-subscription-status {{ $checkoutPayment->status === 'paid' ? 'is-active' : 'is-attention' }}">{{ $paymentLabel }}</span>
                    </div>

                    @if ($isMockPayment)
                        <div class="owner-subscription-notice is-attention">
                            <strong>Pembayaran simulasi (lokal)</strong>
                            <p>Ini bukan QRIS yang dapat dipindai untuk pembayaran nyata. Status pembayaran hanya berubah setelah webhook pengujian diproses.</p>
                        </div>
                    @elseif ($checkoutPayment->qris_payload)
                        <p class="owner-subscription-intro">Data QRIS dari penyedia pembayaran tersedia di bagian detail teknis. Kode QR visual belum tersedia pada halaman ini.</p>
                    @elseif (in_array($checkoutPayment->status, [\App\Models\Payment::STATUS_VERIFYING, \App\Models\Payment::STATUS_RECONCILIATION_REQUIRED], true))
                        <p class="owner-subscription-notice is-attention">Pembayaran sedang diperiksa. Jangan membuat transaksi kedua sebelum pemeriksaan selesai.</p>
                    @endif

                    @if ($checkoutPayment->qris_payload)
                        <details class="owner-subscription-technical">
                            <summary>{{ $isMockPayment ? 'Lihat data pengujian' : 'Lihat detail teknis QRIS' }}</summary>
                            <div>
                                <label class="ui-label" for="subscription-qris-payload">Payload transaksi</label>
                                <textarea id="subscription-qris-payload" class="ui-input w-full" rows="3" readonly>{{ $checkoutPayment->qris_payload }}</textarea>
                            </div>
                        </details>
                    @endif
                </div>
            @endif
        </section>
    @endif

    <section class="owner-finance-panel owner-subscription-plans" aria-labelledby="subscription-plans-heading">
        <div class="owner-subscription-section-head">
            <div>
                <h2 id="subscription-plans-heading">{{ $isStarter ? 'Upgrade paket' : ($isSubscribed ? 'Ubah paket' : 'Pilih paket') }}</h2>
                <p>
                    {{ $isStarter ? 'Upgrade ke Business menambah batas usaha menjadi 5. Tagihan dihitung dari sisa periode Starter.' : ($isSubscribed ? 'Pilih paket lain untuk periode berikutnya. Downgrade berlaku pada perpanjangan berikutnya.' : 'Pilih paket yang sesuai dengan jumlah usaha yang akan Anda kelola.') }}
                </p>
            </div>
        </div>

        @if ($hasOpenPayment)
            <div class="owner-subscription-notice is-attention" role="status">
                Masih ada tagihan yang menunggu pembayaran. Selesaikan atau tunggu hasil pemeriksaan sebelum melakukan perubahan paket lain.
            </div>
        @elseif ($this->plans->isNotEmpty())
            <form wire:submit="checkout" class="owner-subscription-plan-form">
                <div class="owner-subscription-plan-list">
                    @foreach ($this->plans as $plan)
                        <label wire:key="plan-{{ $plan->id }}" class="owner-plan-option">
                            <input type="radio" wire:model="planId" value="{{ $plan->id }}" required>
                            <span class="owner-subscription-plan-info">
                                <strong>{{ $plan->name }}</strong>
                                <span class="owner-subscription-plan-price">Rp{{ number_format($plan->monthly_price, 0, ',', '.') }} <small>/ {{ $plan->duration_days }} hari</small></span>
                                <span>Maks. {{ $plan->max_businesses }} usaha · {{ $plan->max_venues_per_business }} venue per usaha</span>
                                <span>{{ $plan->has_combined_reporting ? 'Laporan gabungan tersedia' : 'Laporan per usaha' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('planId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                @error('subscription') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                @error('plan') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                @error('retainedBusinessId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror

                @if ($current?->plan?->slug === 'business')
                    <div class="owner-subscription-retained">
                        <label class="ui-label" for="retained-business">Usaha yang dipertahankan jika downgrade</label>
                        <p>Paket Starter hanya mencakup satu usaha. Pilih usaha yang tetap aktif jika saat ini Anda mengelola lebih dari satu.</p>
                        <select id="retained-business" wire:model="retainedBusinessId" class="ui-input w-full">
                            <option value="">Pilih usaha</option>
                            @foreach ($this->businesses as $business)
                                <option value="{{ $business->id }}">{{ $business->name }} — {{ str($business->status)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" wire:loading.attr="disabled" wire:target="checkout" class="btn btn-primary">
                    <span wire:loading.remove wire:target="checkout">{{ $isStarter ? 'Upgrade ke Business' : ($isSubscribed ? 'Lanjutkan perubahan paket' : 'Lanjutkan checkout') }}</span>
                    <span wire:loading wire:target="checkout">Memproses...</span>
                </button>
            </form>
        @else
            <p class="owner-subscription-intro">Saat ini tidak ada pilihan paket lain yang tersedia.</p>
        @endif

        @if ($isSubscribed && ! $hasOpenPayment)
            <div class="owner-subscription-renewal">
                <div>
                    <h3>Perpanjang paket saat ini</h3>
                    <p>Lanjutkan paket {{ $current->plan?->name }} untuk periode 30 hari berikutnya.</p>
                </div>
                <button type="button" wire:click="renewCurrentPlan" wire:loading.attr="disabled" wire:target="renewCurrentPlan" class="btn btn-secondary">Perpanjang {{ $current->plan?->name }}</button>
            </div>
        @endif
    </section>

    <section class="owner-finance-panel owner-subscription-history" aria-labelledby="subscription-invoices-heading">
        <h2 id="subscription-invoices-heading">Riwayat tagihan</h2>
        <div class="owner-subscription-history-list">
            @forelse ($this->recentInvoices as $invoice)
                <article wire:key="invoice-{{ $invoice->id }}" class="owner-subscription-history-item">
                    <div>
                        <strong>{{ $invoice->invoice_number }}</strong>
                        <p>{{ $invoice->issued_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · {{ $invoice->invoice_type === 'subscription_upgrade' ? 'Upgrade paket' : 'Langganan' }}</p>
                    </div>
                    <div class="owner-subscription-history-amount">
                        <strong>Rp{{ number_format($invoice->total_amount, 0, ',', '.') }}</strong>
                        <span>{{ match ($invoice->status) { 'paid' => 'Lunas', 'unpaid' => 'Belum dibayar', 'void' => 'Dibatalkan', 'refunded' => 'Dikembalikan', default => str($invoice->status)->replace('_', ' ')->title() } }}</span>
                    </div>
                </article>
            @empty
                <p class="owner-subscription-intro">Belum ada tagihan langganan.</p>
            @endforelse
        </div>
    </section>
</div>