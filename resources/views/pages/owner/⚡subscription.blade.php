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
    public function scheduledRenewalInvoice(): ?Invoice
    {
        $current = $this->currentSubscription;

        return $current && $current->scheduled_plan_id
            ? app(SubscriptionBillingService::class)->paidScheduledRenewalInvoice($current)
            : null;
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

        unset($this->currentSubscription, $this->scheduledRenewalInvoice, $this->recentInvoices, $this->checkoutPayment, $this->checkoutInvoice);
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

    public function renewScheduledPlan(): void
    {
        $current = $this->currentSubscription;

        abort_unless(
            $current
            && in_array($current->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_GRACE_PERIOD], true)
            && $current->scheduled_plan_id !== null,
            403,
        );

        $this->planId = (string) $current->scheduled_plan_id;
        $this->retainedBusinessId = '';
        $this->checkoutIdempotencyKey = '';
        $this->checkout();
    }

    public function updateRetainedBusiness(): void
    {
        $current = $this->currentSubscription;

        abort_unless(
            $current
            && $current->status === Subscription::STATUS_ACTIVE
            && $current->plan?->slug === 'business'
            && $current->scheduledPlan?->slug === 'starter',
            403,
        );

        $this->planId = (string) $current->scheduled_plan_id;
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
        unset($this->currentSubscription, $this->scheduledRenewalInvoice, $this->recentInvoices, $this->checkoutPayment, $this->checkoutInvoice);
    }
};
?>

<div class="site-container owner-core-page owner-subscription-page owner-subscription-v2 py-7 sm:py-9 lg:py-10">
    @php
        $current = $this->currentSubscription;
        $checkoutPayment = $this->checkoutPayment;
        $checkoutInvoice = $this->checkoutInvoice;
        $isSubscribed = $current && in_array($current->status, [
            \App\Models\Subscription::STATUS_TRIALING,
            \App\Models\Subscription::STATUS_ACTIVE,
            \App\Models\Subscription::STATUS_GRACE_PERIOD,
        ], true);
        $isStarter = $isSubscribed && $current->plan?->slug === 'starter';
        $scheduled = $current?->scheduledPlan;
        $paidScheduledRenewalInvoice = $this->scheduledRenewalInvoice;
        $hasOpenPayment = $checkoutPayment
            && in_array($checkoutPayment->status, [
                \App\Models\Payment::STATUS_PENDING,
                \App\Models\Payment::STATUS_VERIFYING,
                \App\Models\Payment::STATUS_RECONCILIATION_REQUIRED,
            ], true)
            && $checkoutInvoice?->status === \App\Models\Invoice::STATUS_UNPAID;
        $showPayment = $hasOpenPayment;
        $isMockPayment = $showPayment && str_starts_with((string) ($checkoutPayment->qris_payload ?? ''), 'MOCK-QRIS:');
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
        $dateTimeZone = 'Asia/Jakarta';
    @endphp

    <header class="owner-core-header owner-subscription-header">
        <div>
            <p class="owner-subscription-eyebrow">Akun owner / Langganan</p>
            <h1>Langganan saya</h1>
            <p>Lihat paket yang sedang digunakan, perubahan berikutnya, dan tagihan di satu tempat.</p>
        </div>
    </header>

    @if (session()->has('status'))
        <p role="status" class="owner-subscription-notice is-success">{{ session('status') }}</p>
    @endif

    @if ($current)
        <section class="owner-subscription-current-card" aria-labelledby="subscription-current-heading">
            <div class="owner-subscription-current-top">
                <div class="owner-subscription-current-title">
                    <span class="owner-subscription-eyebrow">PAKET SAAT INI</span>
                    <h2 id="subscription-current-heading">{{ $current->plan?->name ?? 'Paket tidak tersedia' }}</h2>
                    <p>Fitur dan batas penggunaan yang berlaku untuk akun Anda sekarang.</p>
                </div>
                <span class="owner-subscription-status {{ in_array($current->status, ['active', 'trialing'], true) ? 'is-active' : 'is-attention' }}">{{ $subscriptionLabel }}</span>
            </div>

            <dl class="owner-subscription-facts owner-subscription-facts-v2">
                <div>
                    <dt>Berlaku sampai</dt>
                    <dd>{{ $current->ends_at?->timezone($dateTimeZone)->translatedFormat('d M Y') ?? 'Belum aktif' }}</dd>
                    <small>{{ $current->ends_at ? 'Tanggal akhir periode saat ini' : 'Setelah pembayaran berhasil' }}</small>
                </div>
                <div>
                    <dt>Kuota usaha</dt>
                    <dd>{{ $current->plan?->max_businesses ?? '—' }} usaha</dd>
                    <small>Maksimal usaha dalam satu akun</small>
                </div>
                <div>
                    <dt>Kuota venue</dt>
                    <dd>{{ $current->plan?->max_venues_per_business ?? '—' }} per usaha</dd>
                    <small>Batas venue di masing-masing usaha</small>
                </div>
            </dl>

            @if ($current->status === \App\Models\Subscription::STATUS_GRACE_PERIOD && $current->grace_ends_at)
                <p class="owner-subscription-notice is-attention">Masa tenggang berakhir {{ $current->grace_ends_at->timezone($dateTimeZone)->format('d M Y H:i') }} WIB. Perpanjang langganan agar usaha tidak ditangguhkan.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_SUSPENDED)
                <p class="owner-subscription-notice is-attention">Langganan sedang ditangguhkan. Selesaikan pembayaran untuk memulai pemulihan akses usaha.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_PENDING_REVERIFICATION)
                <p class="owner-subscription-notice is-attention">Pembayaran diproses. Usaha Anda sedang menunggu verifikasi ulang dari Super Admin.</p>
            @elseif ($current->status === \App\Models\Subscription::STATUS_PENDING_PAYMENT)
                <p class="owner-subscription-notice is-attention">Paket belum aktif. Selesaikan tagihan agar usaha dapat mulai dikelola.</p>
            @endif
        </section>
    @else
        <section class="owner-subscription-current-card owner-subscription-empty-card" aria-labelledby="subscription-current-heading">
            <span class="owner-subscription-eyebrow">PAKET SAAT INI</span>
            <h2 id="subscription-current-heading">Belum berlangganan</h2>
            <p>Pilih paket di bawah untuk mulai membuat dan mengelola usaha serta venue.</p>
        </section>
    @endif

    @if ($scheduled)
        <section class="owner-finance-panel owner-subscription-next-panel" aria-labelledby="subscription-next-heading">
            <div class="owner-subscription-panel-heading">
                <div>
                    <span class="owner-subscription-kicker">PERUBAHAN BERIKUTNYA</span>
                    <h2 id="subscription-next-heading">Downgrade sudah dijadwalkan</h2>
                    <p>Anda tetap menikmati paket {{ $current->plan?->name }} sampai periode berakhir. Paket Starter mulai berlaku setelah masa Business selesai dan pembayaran Starter lunas.</p>
                </div>
                <span class="owner-subscription-status is-scheduled">Terjadwal</span>
            </div>

            <div class="owner-subscription-transition" aria-label="Alur perubahan paket">
                <div>
                    <span>Saat ini</span>
                    <strong>{{ $current->plan?->name }}</strong>
                    <small>Aktif sampai {{ $current->ends_at?->timezone($dateTimeZone)->translatedFormat('d M Y') ?? 'akhir periode' }}</small>
                </div>
                <span class="owner-subscription-transition-arrow" aria-hidden="true">→</span>
                <div>
                    <span>Periode berbayar berikutnya</span>
                    <strong>{{ $scheduled->name }}</strong>
                    <small>{{ $scheduled->max_businesses }} usaha · {{ $scheduled->max_venues_per_business }} venue per usaha</small>
                </div>
            </div>

            <div class="owner-subscription-retention-summary">
                <div>
                    <span>Usaha yang tetap dikelola</span>
                    <strong>{{ $current->retainedBusiness?->name ?? 'Belum ditentukan' }}</strong>
                </div>
                <p>Usaha lain akan diarsipkan ketika Starter mulai berlaku. Data dan riwayat transaksi tetap tersimpan.</p>
            </div>

            @if (! $paidScheduledRenewalInvoice && ! $hasOpenPayment && $current->plan?->slug === 'business' && $scheduled->slug === 'starter' && $this->businesses->count() > 1)
                <details class="owner-subscription-adjust-choice">
                    <summary>Ubah usaha yang dipertahankan</summary>
                    <form wire:submit="updateRetainedBusiness">
                        <label for="retained-business-scheduled" class="ui-label">Pilih satu usaha</label>
                        <select id="retained-business-scheduled" wire:model="retainedBusinessId" class="ui-input w-full" required>
                            <option value="">Pilih usaha</option>
                            @foreach ($this->businesses as $business)
                                <option value="{{ $business->id }}">{{ $business->name }}</option>
                            @endforeach
                        </select>
                        @error('retainedBusinessId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        @error('subscription') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="updateRetainedBusiness" class="btn btn-primary">
                            <span wire:loading.remove wire:target="updateRetainedBusiness">Simpan pilihan usaha</span>
                            <span wire:loading wire:target="updateRetainedBusiness">Menyimpan...</span>
                        </button>
                    </form>
                </details>
            @endif

            @if ($paidScheduledRenewalInvoice)
                <div class="owner-subscription-notice is-success" role="status">
                    <strong>Perpanjangan Starter sudah lunas.</strong>
                    Invoice {{ $paidScheduledRenewalInvoice->invoice_number }} telah dibayar.
                    Business dan seluruh usaha Anda tetap aktif sampai {{ $current->ends_at?->timezone($dateTimeZone)->translatedFormat('d M Y') }}.
                    Setelah itu, Starter akan aktif otomatis dan usaha selain yang dipertahankan akan diarsipkan.
                </div>
            @elseif ($hasOpenPayment)
                <div class="owner-subscription-notice is-attention" role="status">
                    <strong>Pembayaran perpanjangan belum selesai.</strong>
                    Selesaikan tagihan di bagian Pembayaran Berlangsung. Selama periode Business belum berakhir, paket dan semua usaha tetap aktif.
                </div>
                <a href="#subscription-checkout" class="btn btn-primary">Lihat tagihan pembayaran</a>
            @else
                <div class="owner-subscription-notice" role="status">
                    <strong>Langkah berikutnya: bayar perpanjangan Starter.</strong>
                    Penjadwalan gratis, tetapi periode Starter berikutnya memerlukan pembayaran
                    Rp{{ number_format($scheduled->monthly_price, 0, ',', '.') }} untuk {{ $scheduled->duration_days }} hari.
                    Pembayaran lebih awal tidak memperpendek masa aktif Business.
                </div>
                @error('subscription') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                @error('plan') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                <button type="button" wire:click="renewScheduledPlan" wire:loading.attr="disabled" wire:target="renewScheduledPlan" class="btn btn-primary">
                    <span wire:loading.remove wire:target="renewScheduledPlan">Bayar perpanjangan {{ $scheduled->name }}</span>
                    <span wire:loading wire:target="renewScheduledPlan">Menyiapkan tagihan...</span>
                </button>
            @endif
        </section>
    @endif

    @if ($showPayment && $checkoutInvoice)
        <section class="owner-finance-panel owner-subscription-checkout" id="subscription-checkout" aria-labelledby="subscription-checkout-heading">
            <div class="owner-subscription-panel-heading">
                <div>
                    <span class="owner-subscription-kicker">PEMBAYARAN BERLANGSUNG</span>
                    <h2 id="subscription-checkout-heading">{{ $isUpgradeInvoice ? 'Selesaikan pembayaran upgrade' : 'Ada tagihan yang perlu diselesaikan' }}</h2>
                    <p>{{ $isUpgradeInvoice ? 'Paket sebelumnya tetap berlaku sampai pembayaran upgrade terkonfirmasi.' : 'Selesaikan tagihan sebelum batas waktu agar langganan dapat diproses.' }}</p>
                </div>
                <span class="owner-subscription-status is-attention">{{ $paymentLabel }}</span>
            </div>

            <div class="owner-subscription-payment-summary">
                <div class="owner-subscription-amount">
                    <span>Total yang perlu dibayar</span>
                    <strong>Rp{{ number_format($checkoutInvoice->total_amount, 0, ',', '.') }}</strong>
                </div>
                <dl class="owner-subscription-payment-meta">
                    <div>
                        <dt>Nomor tagihan</dt>
                        <dd>{{ $checkoutInvoice->invoice_number }}</dd>
                    </div>
                    <div>
                        <dt>Bayar sebelum</dt>
                        <dd>{{ $checkoutPayment->expires_at?->timezone($dateTimeZone)->format('d M Y, H:i') ?? 'Belum ditentukan' }} WIB</dd>
                    </div>
                </dl>
            </div>

            @if ($isMockPayment)
                <p class="owner-subscription-notice is-attention"><strong>Mode simulasi pembayaran.</strong> QRIS ini hanya untuk pengujian lokal. Status akan berubah setelah webhook pengujian diterima.</p>
            @elseif ($checkoutPayment->qris_payload)
                <p class="owner-subscription-notice">Kode QR untuk dipindai belum tersedia di halaman ini. Data transaksi dapat dilihat pada detail pembayaran.</p>
            @elseif (in_array($checkoutPayment->status, [\App\Models\Payment::STATUS_VERIFYING, \App\Models\Payment::STATUS_RECONCILIATION_REQUIRED], true))
                <p class="owner-subscription-notice is-attention">Pembayaran sedang diperiksa. Tunggu hasil verifikasi sebelum membuat transaksi lain.</p>
            @endif

            @if ($checkoutPayment->qris_payload)
                <details class="owner-subscription-technical">
                    <summary>{{ $isMockPayment ? 'Detail simulasi pembayaran' : 'Detail teknis pembayaran' }}</summary>
                    <div>
                        <label class="ui-label" for="subscription-qris-payload">Payload transaksi</label>
                        <textarea id="subscription-qris-payload" class="ui-input w-full" rows="3" readonly>{{ $checkoutPayment->qris_payload }}</textarea>
                    </div>
                </details>
            @endif
        </section>
    @elseif ($checkoutMessage !== '' && ! $checkoutScheduled && ! $scheduled)
        <p role="status" class="owner-subscription-notice is-success">{{ $checkoutMessage }}</p>
    @endif

    @unless ($scheduled)
        <section class="owner-finance-panel owner-subscription-plans" aria-labelledby="subscription-plans-heading">
            <div class="owner-subscription-panel-heading">
                <div>
                    <span class="owner-subscription-kicker">KELOLA PAKET</span>
                    <h2 id="subscription-plans-heading">{{ $isStarter ? 'Butuh lebih banyak usaha?' : ($isSubscribed ? 'Ubah ke paket Starter' : 'Pilih paket langganan') }}</h2>
                    <p>
                        @if ($isStarter)
                            Upgrade ke Business untuk mengelola hingga 5 usaha. Biaya upgrade dihitung prorata dari sisa masa aktif Starter.
                        @elseif ($isSubscribed)
                            Turunkan paket menjadi Starter untuk periode berbayar berikutnya. Paket Business tetap aktif sampai masa berlakunya selesai.
                        @else
                            Tentukan paket yang sesuai dengan jumlah usaha yang akan dikelola.
                        @endif
                    </p>
                </div>
            </div>

            @if ($hasOpenPayment)
                <div class="owner-subscription-notice is-attention" role="status">
                    Selesaikan pembayaran yang masih berlangsung sebelum membuat tagihan atau perubahan paket baru.
                </div>
            @elseif ($this->plans->isNotEmpty())
                <form wire:submit="checkout" class="owner-subscription-plan-form">
                    <div class="owner-subscription-plan-list">
                        @foreach ($this->plans as $plan)
                            <label wire:key="plan-{{ $plan->id }}" class="owner-plan-option owner-subscription-plan-choice">
                                <input type="radio" wire:model="planId" value="{{ $plan->id }}" required>
                                <span class="owner-subscription-plan-info">
                                    <span class="owner-subscription-plan-name">{{ $plan->name }}</span>
                                    <span class="owner-subscription-plan-price">Rp{{ number_format($plan->monthly_price, 0, ',', '.') }} <small>/ {{ $plan->duration_days }} hari</small></span>
                                    <span>{{ $plan->max_businesses }} usaha · {{ $plan->max_venues_per_business }} venue per usaha</span>
                                    <span>{{ $plan->has_combined_reporting ? 'Termasuk laporan gabungan usaha' : 'Laporan untuk setiap usaha' }}</span>
                                </span>
                                <span class="owner-subscription-plan-select" aria-hidden="true">Pilih</span>
                            </label>
                        @endforeach
                    </div>

                    @error('planId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    @error('subscription') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    @error('plan') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    @error('retainedBusinessId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror

                    @if ($current?->plan?->slug === 'business' && $this->businesses->count() > 1)
                        <div class="owner-subscription-retained">
                            <label class="ui-label" for="retained-business">Usaha yang akan dipertahankan</label>
                            <p>Starter hanya mendukung satu usaha. Pilih usaha yang tetap dikelola saat downgrade berlaku.</p>
                            <select id="retained-business" wire:model="retainedBusinessId" class="ui-input w-full" required>
                                <option value="">Pilih usaha</option>
                                @foreach ($this->businesses as $business)
                                    <option value="{{ $business->id }}">{{ $business->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($current?->plan?->slug === 'business' && $this->businesses->count() === 1)
                        <p class="owner-subscription-intro">Satu usaha Anda akan otomatis dipertahankan saat beralih ke Starter.</p>
                    @endif

                    <div class="owner-subscription-form-footer">
                        <p>
                            @if ($isStarter)
                                Tagihan upgrade dibuat setelah Anda melanjutkan. Tanggal akhir paket tidak berubah.
                            @elseif ($isSubscribed)
                                Tidak ada pembayaran saat menjadwalkan downgrade.
                            @else
                                Tagihan pembayaran dibuat setelah Anda melanjutkan.
                            @endif
                        </p>
                        <button type="submit" wire:loading.attr="disabled" wire:target="checkout" class="btn btn-primary">
                            <span wire:loading.remove wire:target="checkout">{{ $isStarter ? 'Lanjutkan upgrade' : ($isSubscribed ? 'Jadwalkan downgrade' : 'Lanjutkan pembayaran') }}</span>
                            <span wire:loading wire:target="checkout">Memproses...</span>
                        </button>
                    </div>
                </form>
            @else
                <p class="owner-subscription-intro">Pilihan paket lainnya belum tersedia.</p>
            @endif

            @if ($isSubscribed && ! $hasOpenPayment)
                <div class="owner-subscription-renewal">
                    <div>
                        <h3>Tetap dengan paket {{ $current->plan?->name }}?</h3>
                        <p>Perpanjang paket yang sedang aktif untuk periode 30 hari berikutnya.</p>
                    </div>
                    <button type="button" wire:click="renewCurrentPlan" wire:loading.attr="disabled" wire:target="renewCurrentPlan" class="btn btn-secondary">Perpanjang paket ini</button>
                </div>
            @endif
        </section>
    @endunless

    <section class="owner-finance-panel owner-subscription-history" aria-labelledby="subscription-invoices-heading">
        <div class="owner-subscription-panel-heading">
            <div>
                <span class="owner-subscription-kicker">CATATAN PEMBAYARAN</span>
                <h2 id="subscription-invoices-heading">Riwayat tagihan</h2>
                <p>Daftar tagihan langganan dan upgrade sebelumnya. Tagihan lunas tidak perlu dibayar kembali.</p>
            </div>
        </div>
        <div class="owner-subscription-history-list">
            @forelse ($this->recentInvoices as $invoice)
                <article wire:key="invoice-{{ $invoice->id }}" class="owner-subscription-history-item">
                    <div class="owner-subscription-history-main">
                        <strong>{{ $invoice->invoice_type === 'subscription_upgrade' ? 'Upgrade paket' : 'Langganan' }}</strong>
                        <span>{{ $invoice->invoice_number }}</span>
                        <small>{{ $invoice->issued_at?->timezone($dateTimeZone)->format('d M Y, H:i') }} WIB</small>
                    </div>
                    <div class="owner-subscription-history-amount">
                        <strong>Rp{{ number_format($invoice->total_amount, 0, ',', '.') }}</strong>
                        <span class="owner-subscription-history-status {{ $invoice->status === 'paid' ? 'is-paid' : ($invoice->status === 'unpaid' ? 'is-unpaid' : '') }}">{{ match ($invoice->status) { 'paid' => 'Lunas', 'unpaid' => 'Belum dibayar', 'void' => 'Dibatalkan', 'refunded' => 'Dikembalikan', default => str($invoice->status)->replace('_', ' ')->title() } }}</span>
                    </div>
                </article>
            @empty
                <p class="owner-subscription-intro">Belum ada riwayat tagihan.</p>
            @endforelse
        </div>
    </section>
</div>
