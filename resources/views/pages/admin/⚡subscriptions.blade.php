<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Langganan Owner')] class extends Component {
    public string $ownerId = '';

    public string $planId = '';

    public string $reason = '';

    public string $search = '';

    #[Computed]
    public function owners()
    {
        $this->requireSuperAdmin();

        return User::query()
            ->where('role', User::ROLE_OWNER)
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);
                $query->where(fn ($nested) => $nested->where('name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%'));
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function plans()
    {
        $this->requireSuperAdmin();

        return SubscriptionPlan::query()->where('is_active', true)->orderBy('monthly_price')->get();
    }

    #[Computed]
    public function subscriptions()
    {
        $this->requireSuperAdmin();

        return Subscription::query()
            ->with(['owner:id,name,email', 'plan:id,name,slug,monthly_price'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);
                $query->whereHas('owner', fn ($owner) => $owner->where('name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%'));
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin() && $user->status === 'active', 403);

        return $user;
    }

    public function grantTrial(SubscriptionLifecycleService $lifecycle): void
    {
        $admin = $this->requireSuperAdmin();

        $data = $this->validate([
            'ownerId' => ['required', 'integer', 'exists:users,id'],
            'planId' => ['required', 'integer', 'exists:subscription_plans,id'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
        ], [
            'ownerId.required' => 'Pilih Owner.',
            'planId.required' => 'Pilih paket trial.',
            'reason.required' => 'Alasan trial wajib diisi.',
            'reason.min' => 'Alasan trial minimal 12 karakter.',
            'reason.max' => 'Alasan trial maksimal 2.000 karakter.',
        ]);

        $owner = User::query()
            ->where('role', User::ROLE_OWNER)
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->findOrFail((int) $data['ownerId']);
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail((int) $data['planId']);

        $subscription = $lifecycle->grantManualTrial($admin, $owner, $plan, trim($data['reason']));

        $this->reset(['ownerId', 'planId', 'reason']);
        unset($this->owners, $this->plans, $this->subscriptions);
        session()->flash('status', 'Trial satu periode paket berhasil diberikan kepada ' . $owner->name . '.');
    }
};
?>

<div class="site-container space-y-6 py-6 sm:py-10">
    <header>
        <p class="eyebrow"><span class="eyebrow-dot"></span> Platform / Langganan</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-[-0.05em] sm:text-4xl">Langganan Owner</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--ink-soft)]">Kelola status langganan dan berikan trial manual kepada Owner hasil akuisisi. Trial memerlukan alasan, tercatat di audit log, dan tidak dapat ditumpuk dengan langganan yang masih berjalan.</p>
    </header>

    @if (session()->has('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <section class="ui-panel p-5 sm:p-7">
        <h2 class="text-xl font-extrabold">Berikan trial manual</h2>
        <form wire:submit="grantTrial" class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="ui-label" for="trial-owner">Owner aktif dan terverifikasi</label>
                <select id="trial-owner" wire:model="ownerId" class="ui-input w-full" required>
                    <option value="">Pilih Owner</option>
                    @foreach ($this->owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }} — {{ $owner->email }}</option>
                    @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="ui-label" for="trial-plan">Paket trial</label>
                <select id="trial-plan" wire:model="planId" class="ui-input w-full" required>
                    <option value="">Pilih paket</option>
                    @foreach ($this->plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }} · Rp{{ number_format($plan->monthly_price, 0, ',', '.') }} / {{ $plan->duration_days }} hari</option>
                    @endforeach
                </select>
                @error('planId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="ui-label" for="trial-reason">Alasan pemberian trial</label>
                <textarea id="trial-reason" wire:model="reason" class="ui-input w-full" rows="3" minlength="12" maxlength="2000" required placeholder="Contoh: hasil akuisisi Owner melalui kunjungan lapangan"></textarea>
                @error('reason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                @error('owner') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                @error('subscription') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Berikan trial</button>
            </div>
        </form>
    </section>

    <section class="ui-panel p-4 sm:p-6">
        <div>
            <label class="ui-label" for="subscription-search">Cari Owner</label>
            <input id="subscription-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input w-full" placeholder="Nama atau email Owner">
        </div>
        <div class="mt-5 space-y-3">
            @forelse ($this->subscriptions as $subscription)
                <article wire:key="subscription-{{ $subscription->id }}" class="rounded-xl border border-[var(--border)] p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-bold">{{ $subscription->owner?->name }} <span class="font-normal text-[var(--ink-soft)]">· {{ $subscription->owner?->email }}</span></p>
                            <p class="mt-1 text-sm">{{ $subscription->plan?->name }} · {{ $subscription->is_trial ? 'Trial manual' : 'Langganan berbayar' }}</p>
                            <p class="mt-1 text-xs text-[var(--ink-soft)]">Mulai {{ $subscription->starts_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · Berakhir {{ $subscription->ends_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            @if ($subscription->grace_ends_at)
                                <p class="mt-1 text-xs text-[var(--ink-soft)]">Batas grace: {{ $subscription->grace_ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
                            @endif
                        </div>
                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ in_array($subscription->status, ['active', 'trialing'], true) ? 'bg-emerald-100 text-emerald-800' : ($subscription->status === 'grace_period' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700') }}">{{ str($subscription->status)->replace('_', ' ')->title() }}</span>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-strong)] p-6 text-center text-sm text-[var(--ink-soft)]">Belum ada langganan yang sesuai pencarian.</div>
            @endforelse
        </div>
        <p class="mt-4 text-xs leading-5 text-[var(--ink-soft)]">Daftar dibatasi 100 langganan terbaru. Pembelian/perpanjangan via QRIS, perubahan paket, dan pengelolaan harga paket akan dilengkapi pada tahap berikutnya.</p>
    </section>
</div>
