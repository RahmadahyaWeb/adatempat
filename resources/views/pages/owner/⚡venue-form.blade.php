<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateField;
use App\Models\VenueFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data venue')] class extends Component {
    public string $businessPublicId;

    public ?string $venuePublicId = null;

    public string $name = '';

    public string $categoryId = '';

    public string $description = '';

    public string $venueTemplateId = '';

    public string $capacityPersons = '';

    public array $facilities = [];

    public array $templateValues = [];

    public function mount(string $business, ?string $venue = null): void
    {
        $businessRecord = auth()->user()
            ->businesses()
            ->where('public_id', $business)
            ->firstOrFail();

        Gate::authorize('view', $businessRecord);

        abort_unless($businessRecord->status !== Business::STATUS_SUSPENDED, 403);

        $this->businessPublicId = (string) $businessRecord->public_id;

        if ($venue !== null) {
            $record = $businessRecord->venues()
                ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField'])
                ->where('public_id', $venue)
                ->firstOrFail();

            Gate::authorize('view', $record);

            abort_unless($record->status !== Venue::STATUS_ARCHIVED, 403);

            $this->venuePublicId = (string) $record->public_id;
            $this->name = $record->name;
            $this->categoryId = (string) $record->category_id;
            $this->description = (string) ($record->description ?? '');
            $this->venueTemplateId = (string) ($record->venue_template_id ?? '');
            $this->capacityPersons = (string) ($record->capacity_persons ?? '');
            $this->facilities = array_values($record->facilities_json ?? []);
            $this->loadTemplateValuesFromVenue($record);
        }
    }

    #[Computed]
    public function currentBusiness(): Business
    {
        return auth()->user()
            ->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();
    }

    #[Computed]
    public function currentVenue(): ?Venue
    {
        if ($this->venuePublicId === null) {
            return null;
        }

        return $this->currentBusiness
            ->venues()
            ->with('category')
            ->where('public_id', $this->venuePublicId)
            ->firstOrFail();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function templates(): Collection
    {
        if ($this->categoryId === '' || ! ctype_digit($this->categoryId)) {
            return new Collection();
        }

        $currentTemplateId = ctype_digit($this->venueTemplateId) ? (int) $this->venueTemplateId : null;

        return VenueTemplate::query()
            ->with('fields')
            ->where('category_id', (int) $this->categoryId)
            ->where(function ($query) use ($currentTemplateId): void {
                $query->where('is_active', true);

                if ($currentTemplateId !== null) {
                    $query->orWhereKey($currentTemplateId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function updatedCategoryId(): void
    {
        $this->resetValidation(['categoryId', 'venueTemplateId', 'templateValues']);
        $this->templateValues = [];

        $template = VenueTemplate::query()
            ->where('category_id', ctype_digit($this->categoryId) ? (int) $this->categoryId : 0)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();

        $this->venueTemplateId = $template ? (string) $template->getKey() : '';
        $this->resetTemplateValuesForSelectedTemplate();
        unset($this->templates);
    }

    public function updatedVenueTemplateId(): void
    {
        $this->resetValidation('templateValues');
        $this->templateValues = [];
        $this->resetTemplateValuesForSelectedTemplate();
        unset($this->templates);
    }

    private function resetTemplateValuesForSelectedTemplate(): void
    {
        if ($this->venueTemplateId === '' || ! ctype_digit($this->venueTemplateId)) {
            $this->templateValues = [];

            return;
        }

        $template = VenueTemplate::query()
            ->with('fields')
            ->whereKey((int) $this->venueTemplateId)
            ->where('category_id', ctype_digit($this->categoryId) ? (int) $this->categoryId : 0)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return;
        }

        foreach ($template->fields as $field) {
            $this->templateValues[$field->field_key] = match ($field->input_type) {
                TemplateField::TYPE_BOOLEAN => false,
                TemplateField::TYPE_MULTISELECT => [],
                default => '',
            };
        }
    }

    private function loadTemplateValuesFromVenue(Venue $venue): void
    {
        foreach ($venue->fieldValues as $fieldValue) {
            $field = $fieldValue->templateField;

            if ($field === null) {
                continue;
            }

            $this->templateValues[$field->field_key] = match ($field->input_type) {
                TemplateField::TYPE_BOOLEAN => (bool) ($fieldValue->value_boolean ?? false),
                TemplateField::TYPE_NUMBER => $fieldValue->value_number !== null ? (string) $fieldValue->value_number : '',
                TemplateField::TYPE_MULTISELECT => $fieldValue->value_json ?? [],
                default => (string) ($fieldValue->value_text ?? ''),
            };
        }
    }

    public function availableFacilities(): array
    {
        return [
            'Wi-Fi',
            'AC',
            'Parkir',
            'Toilet',
            'Mushola',
            'Akses kursi roda',
            'Ruang ganti',
        ];
    }

    public function save(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isOwner(), 403);

        $business = $user->businesses()
            ->where('public_id', $this->businessPublicId)
            ->firstOrFail();

        $venue = $this->venuePublicId === null
            ? null
            : $business->venues()
                ->with(['fieldValues.templateField'])
                ->where('public_id', $this->venuePublicId)
                ->firstOrFail();

        if ($venue === null) {
            Gate::authorize('create', [Venue::class, $business]);
        } else {
            Gate::authorize('update', $venue);
        }

        $facilityOptions = $this->availableFacilities();
        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')],
                'description' => ['nullable', 'string', 'max:10000'],
                'venueTemplateId' => ['nullable', 'integer'],
                'capacityPersons' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'facilities' => ['array'],
                'facilities.*' => [Rule::in($facilityOptions)],
            ],
            [
                'name.required' => 'Nama venue wajib diisi.',
                'name.max' => 'Nama venue maksimal 255 karakter.',
                'categoryId.required' => 'Pilih kategori venue.',
                'categoryId.integer' => 'Kategori yang dipilih tidak valid.',
                'categoryId.exists' => 'Kategori yang dipilih tidak ditemukan.',
                'description.max' => 'Deskripsi maksimal 10.000 karakter.',
                'venueTemplateId.integer' => 'Template yang dipilih tidak valid.',
                'capacityPersons.integer' => 'Kapasitas harus berupa jumlah orang.',
                'capacityPersons.min' => 'Kapasitas minimal 1 orang.',
                'capacityPersons.max' => 'Kapasitas maksimal 100.000 orang.',
                'facilities.*.in' => 'Pilih fasilitas dari daftar yang tersedia.',
            ],
        );

        $category = Category::query()->findOrFail($validated['categoryId']);

        $canRetainInactiveCategory = $venue !== null
            && (int) $venue->category_id === (int) $category->getKey();

        if ($category->status !== Category::STATUS_ACTIVE && ! $canRetainInactiveCategory) {
            $this->addError('categoryId', 'Kategori ini tidak aktif. Pilih kategori lain yang tersedia.');

            return;
        }

        $template = null;
        $selectedTemplateId = trim((string) ($validated['venueTemplateId'] ?? ''));

        if ($selectedTemplateId !== '') {
            $templateQuery = VenueTemplate::query()
                ->with('fields')
                ->whereKey((int) $selectedTemplateId)
                ->where('category_id', $category->getKey());

            $isCurrentTemplate = $venue !== null
                && (int) $venue->venue_template_id === (int) $selectedTemplateId;

            if ($isCurrentTemplate) {
                $templateQuery->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhereKey((int) $this->venueTemplateId);
                });
            } else {
                $templateQuery->where('is_active', true);
            }

            $template = $templateQuery->first();

            if ($template === null) {
                $this->addError('venueTemplateId', 'Template tidak aktif atau tidak sesuai dengan kategori yang dipilih.');

                return;
            }
        } elseif (VenueTemplate::query()
            ->where('category_id', $category->getKey())
            ->where('is_active', true)
            ->exists()) {
            $this->addError('venueTemplateId', 'Pilih template informasi sesuai kategori venue.');

            return;
        }

        $templateRules = [];
        $templateMessages = [];

        if ($template !== null) {
            foreach ($template->fields as $field) {
                $key = 'templateValues.' . $field->field_key;
                $fieldRules = [$field->is_required ? 'required' : 'nullable'];
                $fieldOptions = $field->options_json ?? [];
                $validation = $field->validation_rules_json ?? [];

                switch ($field->input_type) {
                    case TemplateField::TYPE_TEXT:
                    case TemplateField::TYPE_TEXTAREA:
                        $fieldRules[] = 'string';

                        if (isset($validation['max_length'])) {
                            $fieldRules[] = 'max:' . (int) $validation['max_length'];
                        }

                        break;

                    case TemplateField::TYPE_NUMBER:
                        $fieldRules[] = 'numeric';

                        if (isset($validation['min'])) {
                            $fieldRules[] = 'min:' . $validation['min'];
                        }

                        if (isset($validation['max'])) {
                            $fieldRules[] = 'max:' . $validation['max'];
                        }

                        break;

                    case TemplateField::TYPE_BOOLEAN:
                        $fieldRules[] = 'boolean';
                        break;

                    case TemplateField::TYPE_SELECT:
                        $fieldRules[] = Rule::in($fieldOptions);
                        break;

                    case TemplateField::TYPE_MULTISELECT:
                        $fieldRules = [$field->is_required ? 'required' : 'nullable', 'array'];

                        if ($field->is_required) {
                            $fieldRules[] = 'min:1';
                        }

                        $templateRules[$key . '.*'] = [Rule::in($fieldOptions)];
                        $templateMessages[$key . '.required'] = $field->label . ' wajib diisi.';
                        $templateMessages[$key . '.min'] = 'Pilih minimal satu opsi untuk ' . $field->label . '.';
                        $templateMessages[$key . '.*.in'] = 'Pilihan ' . $field->label . ' tidak valid.';

                        break;
                }

                $templateRules[$key] = $fieldRules;
                $templateMessages[$key . '.required'] = $field->label . ' wajib diisi.';
                $templateMessages[$key . '.string'] = $field->label . ' harus berupa teks.';
                $templateMessages[$key . '.numeric'] = $field->label . ' harus berupa angka.';
                $templateMessages[$key . '.boolean'] = $field->label . ' harus berupa pilihan ya/tidak.';
                $templateMessages[$key . '.in'] = 'Pilihan ' . $field->label . ' tidak valid.';
                $templateMessages[$key . '.max'] = $field->label . ' melebihi batas maksimum.';
                $templateMessages[$key . '.min'] = $field->label . ' belum memenuhi nilai minimum.';
            }
        }

        if ($templateRules !== []) {
            $this->validate($templateRules, $templateMessages);
        }

        $venueName = trim($validated['name']);
        $slugBase = Str::slug($venueName) ?: 'venue';
        $slug = $slugBase;
        $suffix = 2;

        while ($business->venues()
            ->where('slug', $slug)
            ->when($this->venuePublicId !== null, fn ($query) => $query->where('public_id', '!=', $this->venuePublicId))
            ->exists()) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        $attributes = [
            'name' => $venueName,
            'slug' => $slug,
            'category_id' => $category->getKey(),
            'venue_template_id' => $template?->getKey(),
            'capacity_persons' => trim((string) ($validated['capacityPersons'] ?? '')) !== ''
                ? (int) $validated['capacityPersons']
                : null,
            'facilities_json' => array_values(array_unique($validated['facilities'] ?? [])),
            'refund_policy_json' => $venue?->refund_policy_json,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ];

        $savedVenue = DB::transaction(function () use ($business, $venue, $attributes, $template): Venue {
            if ($venue === null) {
                $savedVenue = $business->venues()->create($attributes + [
                    'status' => Venue::STATUS_DRAFT,
                    'published_at' => null,
                ]);
            } else {
                $venue->fill($attributes)->save();
                $savedVenue = $venue;
            }

            $templateFieldIds = $template?->fields->modelKeys() ?? [];
            $staleValues = $savedVenue->fieldValues()->whereNotNull('template_field_id');

            if ($templateFieldIds !== []) {
                $staleValues->whereNotIn('template_field_id', $templateFieldIds);
            }

            $staleValues->delete();

            if ($template !== null) {
                foreach ($template->fields as $field) {
                    $rawValue = $this->templateValues[$field->field_key] ?? null;
                    $typedValue = [
                        'value_text' => null,
                        'value_number' => null,
                        'value_boolean' => null,
                        'value_json' => null,
                    ];

                    switch ($field->input_type) {
                        case TemplateField::TYPE_TEXT:
                        case TemplateField::TYPE_TEXTAREA:
                        case TemplateField::TYPE_SELECT:
                            $typedValue['value_text'] = trim((string) ($rawValue ?? '')) !== ''
                                ? trim((string) $rawValue)
                                : null;
                            break;

                        case TemplateField::TYPE_NUMBER:
                            $typedValue['value_number'] = trim((string) ($rawValue ?? '')) !== ''
                                ? (float) $rawValue
                                : null;
                            break;

                        case TemplateField::TYPE_BOOLEAN:
                            $typedValue['value_boolean'] = (bool) ($rawValue ?? false);
                            break;

                        case TemplateField::TYPE_MULTISELECT:
                            $typedValue['value_json'] = array_values(is_array($rawValue) ? $rawValue : []);
                            break;
                    }

                    $savedVenue->fieldValues()->updateOrCreate(
                        ['template_field_id' => $field->getKey()],
                        $typedValue,
                    );
                }
            }

            return $savedVenue;
        });

        if ($venue === null) {
            session()->flash('status', 'Venue berhasil dibuat sebagai draft. Venue belum tampil di katalog publik.');
        } else {
            session()->flash('status', $savedVenue->status === Venue::STATUS_PUBLISHED
                ? 'Perubahan tersimpan dan berlaku pada informasi venue yang dipublikasikan.'
                : 'Perubahan informasi venue tersimpan. Status venue tidak berubah.');
        }

        unset($this->currentBusiness, $this->currentVenue, $this->categories, $this->templates);

        $this->redirectRoute(
            'owner.businesses.venues.edit',
            ['business' => $business->public_id, 'venue' => $savedVenue->public_id],
            navigate: false,
        );
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    @php
        $business = $this->currentBusiness;
        $venue = $this->currentVenue;
        $categories = $this->categories;
        $isEditing = $venue !== null;
        $statusMeta = [
            'draft' => ['Draft', 'bg-[var(--surface-soft)] text-[var(--ink-soft)]'],
            'pending_review' => ['Menunggu peninjauan', 'bg-[var(--warning-bg)] text-[var(--warning-ink)]'],
            'published' => ['Dipublikasikan', 'bg-[var(--success-bg)] text-[var(--success-ink)]'],
            'rejected' => ['Perlu perbaikan', 'bg-[var(--danger-bg)] text-[var(--danger-ink)]'],
            'archived' => ['Diarsipkan', 'bg-[var(--surface-soft)] text-[var(--muted)]'],
        ];
        $venueStatus = $venue ? ($statusMeta[$venue->status] ?? [$venue->status, 'bg-[var(--surface-soft)] text-[var(--ink-soft)]']) : null;
    @endphp

    <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="inline-flex min-h-9 items-center gap-2 text-sm font-semibold text-[var(--ink-soft)] underline-offset-4 hover:text-[var(--brand-forest)] hover:underline">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="m15 18-6-6 6-6"/></svg>
        Kembali ke daftar venue
    </a>

    <header class="mt-5 max-w-3xl">
        <p class="eyebrow"><span class="eyebrow-dot"></span> {{ $isEditing ? 'Edit venue' : 'Venue baru' }}</p>
        <h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl lg:text-5xl">
            {{ $isEditing ? $venue->name : 'Tambahkan venue' }}
        </h1>
        <p class="mt-2 break-words text-sm font-semibold text-[var(--ink-soft)] sm:text-base">{{ $business->name }}</p>
        <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Isi informasi utama terlebih dahulu. Kamu bisa melanjutkan ke pengaturan operasional setelah modul pendukung tersedia.</p>
        @if ($isEditing)
            <span class="mt-4 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $venueStatus[1] }}">{{ $venueStatus[0] }}</span>
        @endif
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif

    @if (!$isEditing)
        <div class="status-note mt-6">
            Venue baru akan disimpan sebagai <strong>Draft</strong>. Tombol pada halaman ini tidak mengajukan verifikasi atau mempublikasikan venue.
        </div>
    @elseif ($venue->status === \App\Models\Venue::STATUS_PUBLISHED)
        <div class="status-note status-success mt-6">
            Venue ini sudah dipublikasikan. Perubahan nama, kategori, dan deskripsi yang disimpan akan langsung tercermin pada informasi publik.
        </div>
    @elseif ($venue->status === \App\Models\Venue::STATUS_PENDING_REVIEW)
        <div class="status-note mt-6">
            Venue sedang menunggu peninjauan. Menyimpan informasi tidak mengubah status peninjauan.
        </div>
    @elseif ($venue->status === \App\Models\Venue::STATUS_REJECTED)
        <div class="status-note mt-6">
            Informasi venue dapat diperbaiki. Menyimpan perubahan tidak mengajukan ulang peninjauan secara otomatis.
        </div>
    @endif

    <div class="mt-7 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-8">
        <section aria-labelledby="venue-form-heading" class="rounded-2xl bg-white p-5 sm:p-7">
            <div>
                <h2 id="venue-form-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Informasi venue</h2>
                <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Informasi ini membantu pengunjung memahami jenis tempat yang ditawarkan.</p>
            </div>

            <form wire:submit="save" class="mt-6 space-y-5">
                <div>
                    <label for="venue-name" class="ui-label">Nama venue <span class="text-[var(--danger-ink)]">*</span></label>
                    <input id="venue-name" type="text" wire:model="name" required maxlength="255" class="ui-input" placeholder="Contoh: Ruang Serbaguna Lantai 2" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="venue-category" class="ui-label">Kategori <span class="text-[var(--danger-ink)]">*</span></label>
                    <select id="venue-category" wire:model="categoryId" required class="ui-input" aria-invalid="{{ $errors->has('categoryId') ? 'true' : 'false' }}">
                        <option value="">Pilih kategori</option>
                        @if ($isEditing && $venue->category && $venue->category->status !== \App\Models\Category::STATUS_ACTIVE)
                            <option value="{{ $venue->category->id }}">{{ $venue->category->name }} (kategori saat ini — tidak aktif)</option>
                        @endif
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    @if ($categories->isEmpty())
                        <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Belum ada kategori aktif. Tambahkan atau aktifkan kategori terlebih dahulu sebelum membuat venue baru.</p>
                    @endif
                </div>

                <div>
                    <label for="venue-description" class="ui-label">Deskripsi venue</label>
                    <textarea id="venue-description" wire:model="description" rows="5" maxlength="10000" class="ui-input resize-y" placeholder="Jelaskan suasana, penggunaan ruang, atau informasi yang penting bagi pengunjung.">{{ $description }}</textarea>
                    <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        @error('description') <p role="alert" class="ui-error">{{ $message }}</p> @else <p class="text-xs leading-5 text-[var(--muted)]">Tuliskan informasi yang benar dan spesifik. Hindari klaim yang belum dapat dipastikan.</p> @enderror
                        <p class="shrink-0 text-xs text-[var(--muted)]">Maks. 10.000 karakter</p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-[var(--border)] pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('owner.businesses.venues.index', ['business' => $business->public_id]) }}" class="btn btn-secondary w-full sm:w-auto">Batal</a>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                        <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Simpan perubahan' : 'Simpan sebagai draft' }}</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </section>

        <aside class="rounded-2xl bg-[var(--surface-soft)] p-5 sm:p-6">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Informasi publikasi</p>
            <h2 class="mt-3 text-lg font-extrabold tracking-[-0.035em]">Tetap jelas dan akurat</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--ink-soft)]">Gunakan nama, kategori, dan deskripsi yang menggambarkan venue sebenarnya.</p>
            <div class="mt-5 space-y-3 border-t border-[var(--border)] pt-5">
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--brand-lime)] text-xs font-extrabold text-[var(--ink)]">01</span>
                    <div><p class="text-sm font-bold">Identitas dasar</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Nama venue dan kategori aktif.</p></div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">02</span>
                    <div><p class="text-sm font-bold">Deskripsi informatif</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Jelaskan informasi yang relevan bagi pengunjung.</p></div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white text-xs font-extrabold text-[var(--ink-soft)]">03</span>
                    <div><p class="text-sm font-bold">Foto, harga, dan jadwal</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Belum termasuk dalam formulir ini karena modul backend-nya belum tersedia.</p></div>
                </div>
            </div>
        </aside>
    </div>
</div>