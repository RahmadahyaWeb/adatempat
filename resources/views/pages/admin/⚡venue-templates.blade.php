<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\TemplateField;
use App\Models\User;
use App\Models\VenueTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Template venue')] class extends Component {
    public string $search = '';

    public bool $showForm = false;

    public string $editingTemplateId = '';

    public string $name = '';

    public string $categoryId = '';

    public bool $isActive = true;

    public array $fields = [];

    #[Computed]
    public function categories(): Collection
    {
        $this->requireSuperAdmin();

        return Category::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function templates(): Collection
    {
        $this->requireSuperAdmin();

        return VenueTemplate::query()
            ->with(['category', 'fields'])
            ->withCount('venues')
            ->when(trim($this->search) !== '', function ($query): void {
                $keyword = trim($this->search);

                $query->where(function ($nested) use ($keyword): void {
                    $nested->where('name', 'like', '%' . $keyword . '%')
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', '%' . $keyword . '%'));
                });
            })
            ->orderBy('category_id')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();
    }

    private function requireSuperAdmin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        return $user;
    }

    private function blankField(int $sortOrder): array
    {
        return [
            'field_key' => '',
            'label' => '',
            'input_type' => TemplateField::TYPE_TEXT,
            'unit_label' => '',
            'options_text' => '',
            'is_required' => false,
            'is_filterable' => false,
            'sort_order' => $sortOrder,
            'min' => '',
            'max' => '',
            'max_length' => '',
        ];
    }

    public function startCreate(): void
    {
        $this->requireSuperAdmin();
        $this->resetForm();
        $this->categoryId = (string) ($this->categories->first()?->id ?? '');
        $this->fields = [$this->blankField(10)];
        $this->showForm = true;
    }

    public function editTemplate(int $templateId): void
    {
        $this->requireSuperAdmin();

        $template = VenueTemplate::query()
            ->with(['category', 'fields'])
            ->whereKey($templateId)
            ->firstOrFail();

        $this->resetForm();
        $this->editingTemplateId = (string) $template->getKey();
        $this->name = $template->name;
        $this->categoryId = (string) $template->category_id;
        $this->isActive = $template->is_active;
        $this->fields = $template->fields->map(function (TemplateField $field): array {
            $rules = $field->validation_rules_json ?? [];

            return [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'input_type' => $field->input_type,
                'unit_label' => (string) ($field->unit_label ?? ''),
                'options_text' => implode("\n", $field->options_json ?? []),
                'is_required' => $field->is_required,
                'is_filterable' => $field->is_filterable,
                'sort_order' => $field->sort_order,
                'min' => isset($rules['min']) ? (string) $rules['min'] : '',
                'max' => isset($rules['max']) ? (string) $rules['max'] : '',
                'max_length' => isset($rules['max_length']) ? (string) $rules['max_length'] : '',
            ];
        })->values()->all();

        if ($this->fields === []) {
            $this->fields = [$this->blankField(10)];
        }

        $this->showForm = true;
    }

    public function addField(): void
    {
        $this->requireSuperAdmin();
        $this->fields[] = $this->blankField((count($this->fields) + 1) * 10);
    }

    public function removeField(int $index): void
    {
        $this->requireSuperAdmin();

        if (! array_key_exists($index, $this->fields)) {
            return;
        }

        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->requireSuperAdmin();
        $this->resetForm();
        unset($this->categories);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingTemplateId',
            'name',
            'categoryId',
            'isActive',
            'fields',
        ]);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $actor = $this->requireSuperAdmin();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', Category::STATUS_ACTIVE)],
            'isActive' => ['required', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:60'],
            'fields.*.field_key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct:strict'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.input_type' => ['required', Rule::in([
                TemplateField::TYPE_TEXT,
                TemplateField::TYPE_TEXTAREA,
                TemplateField::TYPE_NUMBER,
                TemplateField::TYPE_BOOLEAN,
                TemplateField::TYPE_SELECT,
                TemplateField::TYPE_MULTISELECT,
            ])],
            'fields.*.unit_label' => ['nullable', 'string', 'max:30'],
            'fields.*.options_text' => ['nullable', 'string', 'max:4000'],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.is_filterable' => ['required', 'boolean'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'fields.*.min' => ['nullable', 'numeric'],
            'fields.*.max' => ['nullable', 'numeric'],
            'fields.*.max_length' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], [
            'name.required' => 'Nama template wajib diisi.',
            'categoryId.required' => 'Pilih kategori.',
            'categoryId.exists' => 'Pilih kategori yang aktif.',
            'fields.required' => 'Tambahkan setidaknya satu field.',
            'fields.min' => 'Template harus memiliki setidaknya satu field.',
            'fields.max' => 'Maksimal 60 field per template.',
            'fields.*.field_key.required' => 'Key field wajib diisi.',
            'fields.*.field_key.regex' => 'Key harus diawali huruf kecil dan hanya menggunakan huruf kecil, angka, atau garis bawah.',
            'fields.*.field_key.distinct' => 'Key field tidak boleh duplikat dalam satu template.',
            'fields.*.label.required' => 'Label field wajib diisi.',
            'fields.*.input_type.in' => 'Tipe input tidak didukung.',
            'fields.*.options_text.max' => 'Daftar pilihan terlalu panjang.',
            'fields.*.max_length.max' => 'Panjang maksimum tidak boleh melebihi 10.000 karakter.',
        ]);

        $normalisedFields = [];

        foreach ($validated['fields'] as $index => $field) {
            $key = trim($field['field_key']);
            $label = trim($field['label']);
            $options = collect(preg_split('/\r\n|\r|\n/', (string) ($field['options_text'] ?? '')) ?: [])
                ->map(fn (string $option): string => trim($option))
                ->filter(fn (string $option): bool => $option !== '')
                ->unique()
                ->values()
                ->all();

            if (in_array($field['input_type'], [TemplateField::TYPE_SELECT, TemplateField::TYPE_MULTISELECT], true) && $options === []) {
                $this->addError("fields.{$index}.options_text", 'Tambahkan minimal satu pilihan untuk tipe input ini.');

                continue;
            }

            if (
                $field['input_type'] === TemplateField::TYPE_NUMBER
                && $field['min'] !== ''
                && $field['min'] !== null
                && $field['max'] !== ''
                && $field['max'] !== null
                && (float) $field['min'] > (float) $field['max']
            ) {
                $this->addError("fields.{$index}.max", 'Nilai maksimum harus lebih besar atau sama dengan nilai minimum.');

                continue;
            }

            $validationRules = ['required' => (bool) $field['is_required']];

            if ($field['input_type'] === TemplateField::TYPE_NUMBER) {
                if ($field['min'] !== '' && $field['min'] !== null) {
                    $validationRules['min'] = (float) $field['min'];
                }

                if ($field['max'] !== '' && $field['max'] !== null) {
                    $validationRules['max'] = (float) $field['max'];
                }
            }

            if (in_array($field['input_type'], [TemplateField::TYPE_TEXT, TemplateField::TYPE_TEXTAREA], true) && $field['max_length'] !== '' && $field['max_length'] !== null) {
                $validationRules['max_length'] = (int) $field['max_length'];
            }

            $normalisedFields[] = [
                'field_key' => $key,
                'label' => $label,
                'input_type' => $field['input_type'],
                'unit_label' => trim((string) ($field['unit_label'] ?? '')) ?: null,
                'options_json' => in_array($field['input_type'], [TemplateField::TYPE_SELECT, TemplateField::TYPE_MULTISELECT], true) ? $options : null,
                'validation_rules_json' => $validationRules,
                'is_filterable' => (bool) $field['is_filterable'],
                'is_required' => (bool) $field['is_required'],
                'sort_order' => (int) $field['sort_order'],
            ];
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($normalisedFields === []) {
            $this->addError('fields', 'Periksa kembali definisi field sebelum menyimpan.');

            return;
        }

        $templateName = trim($validated['name']);
        $categoryId = (int) $validated['categoryId'];
        $baseTemplateId = $this->editingTemplateId !== '' && ctype_digit($this->editingTemplateId)
            ? (int) $this->editingTemplateId
            : null;

        $duplicateActiveTemplate = VenueTemplate::query()
            ->where('category_id', $categoryId)
            ->where('name', $templateName)
            ->where('is_active', true)
            ->when($baseTemplateId !== null, fn ($query) => $query->where('id', '!=', $baseTemplateId))
            ->exists();

        $baseTemplateSnapshot = $baseTemplateId === null
            ? null
            : VenueTemplate::query()->whereKey($baseTemplateId)->firstOrFail();

        $sameTemplateLineage = $baseTemplateSnapshot !== null
            && (int) $baseTemplateSnapshot->category_id === $categoryId
            && $baseTemplateSnapshot->name === $templateName;

        if ($validated['isActive'] && $duplicateActiveTemplate && ! $sameTemplateLineage) {
            $this->addError('name', 'Kategori sudah memiliki template aktif dengan nama tersebut.');
            return;
        }

        DB::transaction(function () use ($actor, $templateName, $categoryId, $baseTemplateId, $normalisedFields, $validated): void {
            Category::query()->whereKey($categoryId)->lockForUpdate()->firstOrFail();

            $baseTemplate = $baseTemplateId === null
                ? null
                : VenueTemplate::query()->with('fields')->whereKey($baseTemplateId)->lockForUpdate()->firstOrFail();

            if ($validated['isActive']) {
                VenueTemplate::query()
                    ->where('category_id', $categoryId)
                    ->where('name', $templateName)
                    ->where('is_active', true)
                    ->when($baseTemplateId !== null, fn ($query) => $query->where('id', '!=', $baseTemplateId))
                    ->update(['is_active' => false]);
            }

            $beforeState = $baseTemplate === null ? null : [
                'id' => $baseTemplate->getKey(),
                'category_id' => $baseTemplate->category_id,
                'name' => $baseTemplate->name,
                'version' => $baseTemplate->version,
                'is_active' => $baseTemplate->is_active,
                'fields' => $baseTemplate->fields->map(fn (TemplateField $field) => [
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'input_type' => $field->input_type,
                ])->all(),
            ];

            if ($baseTemplate !== null) {
                $baseTemplate->fill(['is_active' => false])->save();
            }

            $nextVersion = ((int) VenueTemplate::query()
                ->where('category_id', $categoryId)
                ->where('name', $templateName)
                ->max('version')) + 1;

            $template = VenueTemplate::query()->create([
                'category_id' => $categoryId,
                'name' => $templateName,
                'version' => $nextVersion,
                'is_active' => (bool) $validated['isActive'],
            ]);

            foreach ($normalisedFields as $field) {
                $template->fields()->create($field);
            }

            AuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => $baseTemplate === null ? 'venue_template.created' : 'venue_template.version_created',
                'auditable_type' => VenueTemplate::class,
                'auditable_id' => $template->getKey(),
                'reason' => null,
                'before_state_json' => $beforeState,
                'after_state_json' => [
                    'id' => $template->getKey(),
                    'category_id' => $template->category_id,
                    'name' => $template->name,
                    'version' => $template->version,
                    'is_active' => $template->is_active,
                    'fields' => $normalisedFields,
                ],
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        $this->resetForm();
        unset($this->templates, $this->categories);
        session()->flash('status', 'Template berhasil disimpan sebagai versi baru. Venue yang menggunakan versi lama tetap mempertahankan data dan definisi sebelumnya.');
    }

    public function deleteUnusedTemplate(int $templateId): void
    {
        $actor = $this->requireSuperAdmin();
        $template = VenueTemplate::query()->with('fields')->whereKey($templateId)->firstOrFail();

        if ($template->is_active) {
            $this->addError('templateDelete', 'Nonaktifkan template aktif dengan membuat versi baru sebelum menghapusnya.');

            return;
        }

        if ($template->venues()->exists()) {
            $this->addError('templateDelete', 'Template yang sudah dipakai venue tidak dapat dihapus. Buat versi baru untuk mengubah definisinya.');

            return;
        }

        DB::transaction(function () use ($actor, $template): void {
            $before = [
                'category_id' => $template->category_id,
                'name' => $template->name,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'fields' => $template->fields->map(fn (TemplateField $field) => ['field_key' => $field->field_key, 'label' => $field->label, 'input_type' => $field->input_type])->all(),
            ];

            $template->fields()->delete();
            $template->delete();

            AuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'actor_role' => $actor->role,
                'action' => 'venue_template.deleted',
                'auditable_type' => VenueTemplate::class,
                'auditable_id' => $template->getKey(),
                'reason' => 'Template belum digunakan venue.',
                'before_state_json' => $before,
                'after_state_json' => null,
                'ip_hash' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
                'created_at' => now(),
            ]);
        });

        unset($this->templates);
        session()->flash('status', 'Template yang belum digunakan berhasil dihapus.');
    }
};
?>

<div class="site-container py-7 sm:py-10 lg:py-12">
    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-3xl">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Administrasi platform</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-[-0.055em] sm:text-4xl">Template venue</h1>
            <p class="mt-3 text-sm leading-7 text-[var(--ink-soft)] sm:text-base">Atur field tambahan berdasarkan kategori. Perubahan definisi disimpan sebagai versi baru agar venue yang memakai versi lama tidak kehilangan arti data yang tersimpan.</p>
        </div>
        @if (!$showForm)
            <button type="button" wire:click="startCreate" class="btn btn-primary w-full shrink-0 sm:w-auto">Buat template</button>
        @endif
    </header>

    @if (session('status'))
        <div role="status" class="status-note status-success mt-6">{{ session('status') }}</div>
    @endif
    @error('templateDelete')
        <div role="alert" class="status-note mt-4">{{ $message }}</div>
    @enderror

    @if ($showForm)
        <section aria-labelledby="template-form-heading" class="mt-7 rounded-2xl bg-white p-5 sm:p-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Konfigurasi template</p>
                    <h2 id="template-form-heading" class="mt-2 text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">{{ $editingTemplateId !== '' ? 'Buat versi template berikutnya' : 'Template baru' }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ink-soft)]">Gunakan key unik yang stabil. Field select dan multiselect wajib memiliki satu pilihan per baris. Jangan memasukkan HTML atau JavaScript.</p>
                </div>
                <button type="button" wire:click="cancelForm" class="btn btn-secondary w-full sm:w-auto">Batal</button>
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="template-name" class="ui-label">Nama template</label>
                        <input id="template-name" type="text" wire:model="name" class="ui-input" maxlength="120" required placeholder="Contoh: Informasi ruang meeting" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                        @error('name') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="template-category" class="ui-label">Kategori</label>
                        <select id="template-category" wire:model="categoryId" class="ui-input" required aria-invalid="{{ $errors->has('categoryId') ? 'true' : 'false' }}">
                            <option value="">Pilih kategori</option>
                            @foreach ($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[var(--ink-soft)]">
                    <input type="checkbox" wire:model="isActive" class="mt-1 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                    <span>
                        <span class="block font-bold text-[var(--ink)]">Jadikan versi aktif</span>
                        <span class="block text-xs leading-5 text-[var(--muted)]">Template aktif tersedia saat Owner membuat atau mengubah venue.</span>
                    </span>
                </label>

                <div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-extrabold tracking-[-0.035em]">Field template</h3>
                            <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Atur label, key, tipe, validasi, pilihan, dan kemampuan filter tiap field.</p>
                        </div>
                        <button type="button" wire:click="addField" class="btn btn-secondary w-full sm:w-auto">Tambah field</button>
                    </div>

                    @error('fields') <p role="alert" class="ui-error mt-2">{{ $message }}</p> @enderror

                    <div class="mt-4 space-y-4">
                        @foreach ($fields as $index => $field)
                            <article wire:key="template-field-{{ $index }}" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Field {{ $index + 1 }}</p>
                                        <h4 class="mt-1 text-base font-extrabold text-[var(--ink)]">{{ $field['label'] ?: 'Field baru' }}</h4>
                                    </div>
                                    <button type="button" wire:click="removeField({{ $index }})" class="btn btn-secondary min-h-9 px-3 py-1.5" aria-label="Hapus field {{ $index + 1 }}">Hapus</button>
                                </div>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label for="field-key-{{ $index }}" class="ui-label">Key</label>
                                        <input id="field-key-{{ $index }}" type="text" wire:model="fields.{{ $index }}.field_key" class="ui-input font-mono text-sm" maxlength="80" required placeholder="meeting_layout" aria-invalid="{{ $errors->has('fields.' . $index . '.field_key') ? 'true' : 'false' }}">
                                        @error("fields.{$index}.field_key") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="field-label-{{ $index }}" class="ui-label">Label untuk Owner</label>
                                        <input id="field-label-{{ $index }}" type="text" wire:model="fields.{{ $index }}.label" class="ui-input" maxlength="120" required placeholder="Tata ruang" aria-invalid="{{ $errors->has('fields.' . $index . '.label') ? 'true' : 'false' }}">
                                        @error("fields.{$index}.label") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="field-type-{{ $index }}" class="ui-label">Tipe input</label>
                                        <select id="field-type-{{ $index }}" wire:model.live="fields.{{ $index }}.input_type" class="ui-input" required>
                                            @foreach (['text' => 'Teks singkat', 'textarea' => 'Teks panjang', 'number' => 'Angka', 'boolean' => 'Ya / Tidak', 'select' => 'Satu pilihan', 'multiselect' => 'Banyak pilihan'] as $type => $label)
                                                <option value="{{ $type }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error("fields.{$index}.input_type") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="field-unit-{{ $index }}" class="ui-label">Satuan (opsional)</label>
                                        <input id="field-unit-{{ $index }}" type="text" wire:model="fields.{{ $index }}.unit_label" class="ui-input" maxlength="30" placeholder="contoh: orang">
                                        @error("fields.{$index}.unit_label") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="field-order-{{ $index }}" class="ui-label">Urutan tampil</label>
                                        <input id="field-order-{{ $index }}" type="number" min="0" max="100000" wire:model="fields.{{ $index }}.sort_order" class="ui-input" required>
                                        @error("fields.{$index}.sort_order") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex flex-col justify-center gap-3">
                                        <label class="flex cursor-pointer items-start gap-2 text-sm text-[var(--ink-soft)]">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_required" class="mt-0.5 size-4 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                            <span>Wajib diisi</span>
                                        </label>
                                        <label class="flex cursor-pointer items-start gap-2 text-sm text-[var(--ink-soft)]">
                                            <input type="checkbox" wire:model="fields.{{ $index }}.is_filterable" class="mt-0.5 size-4 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                            <span>Disiapkan untuk filter</span>
                                        </label>
                                    </div>
                                </div>

                                @if (in_array($field['input_type'], ['select', 'multiselect'], true))
                                    <div class="mt-4">
                                        <label for="field-options-{{ $index }}" class="ui-label">Pilihan (satu opsi per baris)</label>
                                        <textarea id="field-options-{{ $index }}" wire:model="fields.{{ $index }}.options_text" rows="4" class="ui-input resize-y font-mono text-sm" placeholder="Classroom&#10;Boardroom&#10;U-shape"></textarea>
                                        @error("fields.{$index}.options_text") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                @if (in_array($field['input_type'], ['number'], true))
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="field-min-{{ $index }}" class="ui-label">Nilai minimum</label>
                                            <input id="field-min-{{ $index }}" type="number" step="any" wire:model="fields.{{ $index }}.min" class="ui-input" placeholder="Opsional">
                                            @error("fields.{$index}.min") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="field-max-{{ $index }}" class="ui-label">Nilai maksimum</label>
                                            <input id="field-max-{{ $index }}" type="number" step="any" wire:model="fields.{{ $index }}.max" class="ui-input" placeholder="Opsional">
                                            @error("fields.{$index}.max") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                @endif

                                @if (in_array($field['input_type'], ['text', 'textarea'], true))
                                    <div class="mt-4 max-w-sm">
                                        <label for="field-max-length-{{ $index }}" class="ui-label">Panjang maksimum</label>
                                        <input id="field-max-length-{{ $index }}" type="number" min="1" max="10000" wire:model="fields.{{ $index }}.max_length" class="ui-input" placeholder="Opsional">
                                        @error("fields.{$index}.max_length") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-[var(--border)] pt-5 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelForm" class="btn btn-secondary w-full sm:w-auto">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">
                        <span wire:loading.remove wire:target="save">Simpan sebagai versi baru</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section aria-labelledby="template-list-heading" class="mt-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="template-list-heading" class="text-xl font-extrabold tracking-[-0.04em] sm:text-2xl">Daftar template</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Versi lama tetap tersedia untuk menjaga arti data venue yang sudah tersimpan.</p>
            </div>
            <span class="text-sm font-semibold text-[var(--muted)]">{{ $this->templates->count() }} versi</span>
        </div>

        <div class="mt-4">
            <label for="template-search" class="ui-label">Cari template atau kategori</label>
            <input id="template-search" type="search" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="Nama template, misalnya Meeting">
        </div>

        @if ($this->templates->isEmpty())
            <div class="mt-5 rounded-2xl bg-white px-5 py-10 text-center">
                <h3 class="text-lg font-extrabold">Belum ada template yang sesuai.</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Coba kata kunci lain atau buat template baru.</p>
            </div>
        @else
            <div class="mt-5 space-y-3">
                @foreach ($this->templates as $template)
                    <article wire:key="template-{{ $template->id }}" class="rounded-2xl bg-white p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="break-words text-lg font-extrabold tracking-[-0.035em]">{{ $template->name }}</h3>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold {{ $template->is_active ? 'bg-[var(--success-bg)] text-[var(--success-ink)]' : 'bg-[var(--surface-soft)] text-[var(--muted)]' }}">{{ $template->is_active ? 'Aktif' : 'Versi lama / nonaktif' }}</span>
                                </div>
                                <p class="mt-1 text-sm text-[var(--ink-soft)]">{{ $template->category?->name ?? 'Kategori tidak tersedia' }} · Versi {{ $template->version }} · {{ $template->fields->count() }} field · {{ $template->venues_count }} venue menggunakan versi ini</p>
                                @if ($template->fields->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($template->fields as $field)
                                            <span class="inline-flex max-w-full items-center rounded-md bg-[var(--surface-soft)] px-2 py-1 text-xs font-semibold text-[var(--ink-soft)]">{{ $field->label }} <span class="ml-1 font-mono text-[var(--muted)]">({{ $field->input_type }})</span></span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto">
                                <button type="button" wire:click="editTemplate({{ $template->id }})" class="btn btn-secondary w-full sm:w-auto">{{ $template->is_active ? 'Buat versi baru' : 'Duplikasi versi ini' }}</button>
                                @if ($template->venues_count === 0 && !$template->is_active)
                                    <button type="button" wire:click="deleteUnusedTemplate({{ $template->id }})" wire:confirm="Hapus template versi ini? Data yang digunakan venue tidak dapat dihapus." class="btn btn-secondary w-full sm:w-auto">Hapus versi tanpa penggunaan</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
