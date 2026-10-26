<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateField;
use App\Models\OwnerCustomField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Data venue')] class extends Component {
    use WithFileUploads;
    public string $businessPublicId;

    public ?string $venuePublicId = null;

    public string $name = '';

    public string $categoryId = '';

    public string $description = '';

    public string $venueTemplateId = '';

    public string $capacityPersons = '';

    public array $facilities = [];

    public array $templateValues = [];

    public array $customFields = [];

    public array $customFieldValues = [];

    public $mediaUpload = null;

    public string $mediaAltText = '';

    public bool $mediaIsCover = false;

    public array $mediaAltTexts = [];

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
                ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields', 'media'])
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

            if ($this->venueTemplateId === '') {
                $defaultTemplateId = VenueTemplate::query()
                    ->where('category_id', $record->category_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->value('id');

                $this->venueTemplateId = $defaultTemplateId !== null ? (string) $defaultTemplateId : '';
            }

            $this->resetTemplateValuesForSelectedTemplate();
            $this->loadTemplateValuesFromVenue($record);
            $this->loadCustomFieldsFromVenue($record);
            foreach ($record->media as $media) {
                $this->mediaAltTexts[$media->getKey()] = $media->alt_text;
            }
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
            ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields', 'media'])
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
        $this->resetValidation('categoryId');
        $this->resetValidation('venueTemplateId');
        $this->resetValidation('templateValues');
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

    private function loadCustomFieldsFromVenue(Venue $venue): void
    {
        $this->customFields = [];
        $this->customFieldValues = [];

        foreach ($venue->ownerCustomFields->sortBy('sort_order')->values() as $field) {
            $this->customFields[] = [
                'id' => $field->getKey(),
                'field_key' => $field->field_key,
                'label' => $field->label,
                'input_type' => $field->input_type,
                'options_text' => implode("\n", $field->options_json ?? []),
                'sort_order' => $field->sort_order,
            ];

            $value = $venue->fieldValues->first(
                fn ($fieldValue) => (int) $fieldValue->owner_custom_field_id === (int) $field->getKey(),
            );

            $this->customFieldValues[] = match ($field->input_type) {
                OwnerCustomField::TYPE_BOOLEAN => (bool) ($value?->value_boolean ?? false),
                OwnerCustomField::TYPE_NUMBER => $value?->value_number !== null ? (string) $value->value_number : '',
                default => (string) ($value?->value_text ?? ''),
            };
        }
    }

    public function addCustomField(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        if (count($this->customFields) >= 30) {
            $this->addError('customFields', 'Maksimal 30 custom field untuk satu venue.');

            return;
        }

        $this->customFields[] = [
            'id' => null,
            'field_key' => '',
            'label' => '',
            'input_type' => OwnerCustomField::TYPE_TEXT,
            'options_text' => '',
            'sort_order' => (count($this->customFields) + 1) * 10,
        ];
        $this->customFieldValues[] = '';
    }

    public function removeCustomField(int $index): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        if (! array_key_exists($index, $this->customFields)) {
            return;
        }

        unset($this->customFields[$index], $this->customFieldValues[$index]);
        $this->customFields = array_values($this->customFields);
        $this->customFieldValues = array_values($this->customFieldValues);
        $this->resetValidation();
    }

    public function updatedCustomFields(mixed $value, string $key): void
    {
        [$index, $property] = array_pad(explode('.', $key, 2), 2, null);

        if ($property !== 'input_type' || ! ctype_digit((string) $index) || ! isset($this->customFields[(int) $index])) {
            return;
        }

        $this->customFieldValues[(int) $index] = $value === OwnerCustomField::TYPE_BOOLEAN ? false : '';
        $this->resetValidation("customFieldValues.{$index}");
    }

    public function uploadMedia(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $venue = $this->currentVenue;

        if ($venue === null) {
            $this->addError('mediaUpload', 'Simpan venue terlebih dahulu sebelum mengunggah foto.');

            return;
        }

        Gate::authorize('update', $venue);

        $validated = $this->validate([
            'mediaUpload' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mediaAltText' => ['required', 'string', 'max:180'],
            'mediaIsCover' => ['boolean'],
        ], [
            'mediaUpload.required' => 'Pilih foto yang akan diunggah.',
            'mediaUpload.mimetypes' => 'Format foto harus JPEG, PNG, atau WebP. SVG tidak diperbolehkan.',
            'mediaUpload.mimes' => 'Format foto harus JPEG, PNG, atau WebP. SVG tidak diperbolehkan.',
            'mediaUpload.max' => 'Ukuran foto maksimal 5 MB.',
            'mediaAltText.required' => 'Deskripsi singkat foto wajib diisi.',
            'mediaAltText.max' => 'Deskripsi singkat foto maksimal 180 karakter.',
        ]);

        $file = $validated['mediaUpload'];
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->addError('mediaUpload', 'Format foto harus JPEG, PNG, atau WebP. SVG tidak diperbolehkan.');

            return;
        }

        $disk = 'public';
        $path = $file->storePublicly('venues/' . $venue->public_id, $disk);

        $createdMediaId = null;

        try {
            DB::transaction(function () use ($venue, $file, $mimeType, $disk, $path, $validated, &$createdMediaId): void {
                $isCover = (bool) $validated['mediaIsCover'] || ! $venue->media()->exists();

                if ($isCover) {
                    $venue->media()->update(['is_cover' => false]);
                }

                $sortOrder = ((int) $venue->media()->max('sort_order')) + 10;

                $createdMedia = $venue->media()->create([
                    'disk' => $disk,
                    'file_path' => $path,
                    'mime_type' => $mimeType,
                    'file_size_bytes' => (int) $file->getSize(),
                    'alt_text' => trim($validated['mediaAltText']),
                    'is_cover' => $isCover,
                    'sort_order' => $sortOrder,
                ]);
                $createdMediaId = $createdMedia->getKey();
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        $this->reset(['mediaUpload', 'mediaAltText', 'mediaIsCover']);
        if ($createdMediaId !== null) {
            $this->mediaAltTexts[$createdMediaId] = trim($validated['mediaAltText']);
        }
        unset($this->currentVenue);
        session()->flash('status', 'Foto venue berhasil diunggah.');
    }

    public function updateMediaAltText(int $mediaId): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $venue = $this->currentVenue;
        abort_unless($venue !== null, 404);
        Gate::authorize('update', $venue);

        $key = 'mediaAltTexts.' . $mediaId;
        $this->validate([
            $key => ['required', 'string', 'max:180'],
        ], [
            $key . '.required' => 'Deskripsi singkat foto wajib diisi.',
            $key . '.max' => 'Deskripsi singkat foto maksimal 180 karakter.',
        ]);

        $media = $venue->media()->whereKey($mediaId)->firstOrFail();
        $media->update(['alt_text' => trim((string) $this->mediaAltTexts[$mediaId])]);

        unset($this->currentVenue);
        session()->flash('status', 'Deskripsi foto berhasil diperbarui.');
    }

    public function setMediaCover(int $mediaId): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $venue = $this->currentVenue;
        abort_unless($venue !== null, 404);
        Gate::authorize('update', $venue);

        $media = $venue->media()->whereKey($mediaId)->firstOrFail();

        DB::transaction(function () use ($venue, $media): void {
            $venue->media()->update(['is_cover' => false]);
            $media->update(['is_cover' => true]);
        });

        unset($this->currentVenue);
        session()->flash('status', 'Foto sampul berhasil diperbarui.');
    }

    public function moveMedia(int $mediaId, string $direction): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);
        abort_unless(in_array($direction, ['up', 'down'], true), 422);

        $venue = $this->currentVenue;
        abort_unless($venue !== null, 404);
        Gate::authorize('update', $venue);

        $items = $venue->media()->orderBy('sort_order')->orderBy('id')->get()->all();
        $ids = array_map(fn ($item) => (int) $item->getKey(), $items);
        $index = array_search($mediaId, $ids, true);

        if ($index === false) {
            abort(404);
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($items[$targetIndex])) {
            return;
        }

        $current = $items[$index];
        $items[$index] = $items[$targetIndex];
        $items[$targetIndex] = $current;

        DB::transaction(function () use ($items): void {
            foreach ($items as $index => $item) {
                $item->update(['sort_order' => ($index + 1) * 10]);
            }
        });

        unset($this->currentVenue);
    }

    public function deleteMedia(int $mediaId): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $venue = $this->currentVenue;
        abort_unless($venue !== null, 404);
        Gate::authorize('update', $venue);

        $media = $venue->media()->whereKey($mediaId)->firstOrFail();
        $disk = $media->disk;
        $path = $media->file_path;
        $wasCover = $media->is_cover;

        DB::transaction(function () use ($venue, $media, $wasCover): void {
            $media->delete();

            if ($wasCover) {
                $replacement = $venue->media()->orderBy('sort_order')->orderBy('id')->first();
                $replacement?->update(['is_cover' => true]);
            }
        });

        Storage::disk($disk)->delete($path);
        unset($this->currentVenue, $this->mediaAltTexts[$mediaId]);
        session()->flash('status', 'Foto venue berhasil dihapus.');
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
                ->with(['fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields'])
                ->where('public_id', $this->venuePublicId)
                ->firstOrFail();

        if ($venue === null) {
            Gate::authorize('create', [Venue::class, $business]);
        } else {
            Gate::authorize('update', $venue);
        }

        $this->customFields = array_values($this->customFields);
        $this->customFieldValues = array_values($this->customFieldValues);

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
                'customFields' => ['array', 'max:30'],
                'customFields.*.id' => ['nullable', 'integer'],
                'customFields.*.field_key' => ['nullable', 'string', 'max:80'],
                'customFields.*.label' => ['required', 'string', 'max:120'],
                'customFields.*.input_type' => ['required', Rule::in([OwnerCustomField::TYPE_TEXT, OwnerCustomField::TYPE_NUMBER, OwnerCustomField::TYPE_BOOLEAN, OwnerCustomField::TYPE_SELECT])],
                'customFields.*.options_text' => ['nullable', 'string', 'max:4000'],
                'customFields.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
                'customFieldValues' => ['array'],
                'customFieldValues.*' => ['nullable'],
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
                'customFields.max' => 'Maksimal 30 custom field untuk satu venue.',
                'customFields.*.label.required' => 'Label custom field wajib diisi.',
                'customFields.*.label.max' => 'Label custom field maksimal 120 karakter.',
                'customFields.*.input_type.in' => 'Tipe custom field tidak didukung.',
                'customFields.*.options_text.max' => 'Daftar pilihan terlalu panjang.',
            ],
        );

        $existingCustomFieldIds = array_map('intval', $venue?->ownerCustomFields()->pluck('id')->all() ?? []);

        foreach ($validated['customFields'] ?? [] as $index => $customField) {
            $fieldId = $customField['id'] ?? null;

            if ($fieldId !== null && ! in_array((int) $fieldId, $existingCustomFieldIds, true)) {
                $this->addError("customFields.{$index}.id", 'Custom field tidak terkait dengan venue ini.');

                return;
            }
        }

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
                $validation = $field->validation_rules_json ?? [];
                $isRequired = $field->is_required || (bool) ($validation['required'] ?? false);
                $fieldRules = [$isRequired ? 'required' : 'nullable'];
                $fieldOptions = $field->options_json ?? [];

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
                        $fieldRules = [$isRequired ? 'required' : 'nullable', 'array'];

                        if ($isRequired) {
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

        $normalisedCustomFields = [];
        $normalisedCustomValues = [];
        $customFieldRules = [];
        $customFieldMessages = [];
        $customFieldDefinitionErrors = false;

        foreach ($validated['customFields'] ?? [] as $index => $field) {
            $options = collect(preg_split('/\\r\\n|\\r|\\n/', (string) ($field['options_text'] ?? '')) ?: [])
                ->map(fn (string $option): string => trim($option))
                ->filter(fn (string $option): bool => $option !== '')
                ->unique()
                ->values()
                ->all();

            if ($field['input_type'] === OwnerCustomField::TYPE_SELECT && $options === []) {
                $this->addError("customFields.{$index}.options_text", 'Tambahkan minimal satu pilihan untuk custom field bertipe pilihan.');
                $customFieldDefinitionErrors = true;
                continue;
            }

            $value = $this->customFieldValues[$index] ?? null;

            if ($field['input_type'] !== OwnerCustomField::TYPE_BOOLEAN && is_string($value) && trim($value) === '') {
                $value = null;
            }

            $valueKey = "customFieldValues.{$index}";
            $customFieldRules[$valueKey] = match ($field['input_type']) {
                OwnerCustomField::TYPE_TEXT => ['nullable', 'string', 'max:2000'],
                OwnerCustomField::TYPE_NUMBER => ['nullable', 'numeric'],
                OwnerCustomField::TYPE_BOOLEAN => ['boolean'],
                OwnerCustomField::TYPE_SELECT => ['nullable', 'string', Rule::in($options)],
                default => ['prohibited'],
            };
            $customFieldMessages[$valueKey . '.string'] = $field['label'] . ' harus berupa teks.';
            $customFieldMessages[$valueKey . '.numeric'] = $field['label'] . ' harus berupa angka.';
            $customFieldMessages[$valueKey . '.boolean'] = $field['label'] . ' harus berupa pilihan ya/tidak.';
            $customFieldMessages[$valueKey . '.in'] = 'Pilihan untuk ' . $field['label'] . ' tidak valid.';
            $customFieldMessages[$valueKey . '.max'] = $field['label'] . ' maksimal 2.000 karakter.';

            $normalisedCustomFields[] = [
                'id' => isset($field['id']) && $field['id'] !== '' ? (int) $field['id'] : null,
                'label' => trim($field['label']),
                'input_type' => $field['input_type'],
                'options_json' => $field['input_type'] === OwnerCustomField::TYPE_SELECT ? $options : null,
                'sort_order' => (int) $field['sort_order'],
            ];
            $normalisedCustomValues[] = $value;
        }

        if ($customFieldDefinitionErrors) {
            return;
        }

        $this->customFieldValues = $normalisedCustomValues;

        if ($customFieldRules !== []) {
            $this->validate($customFieldRules, $customFieldMessages);
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

        $savedVenue = DB::transaction(function () use ($business, $venue, $attributes, $template, $normalisedCustomFields, $normalisedCustomValues): Venue {
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

            $keptCustomFieldIds = [];

            foreach ($normalisedCustomFields as $index => $fieldData) {
                $fieldId = $fieldData['id'];
                $fieldAttributes = [
                    'label' => $fieldData['label'],
                    'input_type' => $fieldData['input_type'],
                    'options_json' => $fieldData['options_json'],
                    'sort_order' => $fieldData['sort_order'],
                ];

                if ($fieldId !== null) {
                    $customField = $savedVenue->ownerCustomFields()->whereKey($fieldId)->firstOrFail();
                    $customField->fill($fieldAttributes)->save();
                } else {
                    $baseKey = substr(Str::slug($fieldData['label'], '_') ?: 'field', 0, 70);
                    $fieldKey = $baseKey;
                    $suffix = 2;

                    while ($savedVenue->ownerCustomFields()->where('field_key', $fieldKey)->exists()) {
                        $fieldKey = substr($baseKey, 0, 75) . '_' . $suffix;
                        $suffix++;
                    }

                    $customField = $savedVenue->ownerCustomFields()->create($fieldAttributes + [
                        'field_key' => $fieldKey,
                        'moderation_status' => OwnerCustomField::STATUS_ACTIVE,
                        'moderation_reason' => null,
                    ]);
                }

                $keptCustomFieldIds[] = $customField->getKey();
                $rawValue = $normalisedCustomValues[$index] ?? null;
                $typedValue = [
                    'template_field_id' => null,
                    'value_text' => null,
                    'value_number' => null,
                    'value_boolean' => null,
                    'value_json' => null,
                ];

                switch ($customField->input_type) {
                    case OwnerCustomField::TYPE_TEXT:
                    case OwnerCustomField::TYPE_SELECT:
                        $typedValue['value_text'] = is_string($rawValue) && trim($rawValue) !== '' ? trim($rawValue) : null;
                        break;
                    case OwnerCustomField::TYPE_NUMBER:
                        $typedValue['value_number'] = $rawValue !== null && $rawValue !== '' ? (float) $rawValue : null;
                        break;
                    case OwnerCustomField::TYPE_BOOLEAN:
                        $typedValue['value_boolean'] = (bool) $rawValue;
                        break;
                }

                $savedVenue->fieldValues()->updateOrCreate(
                    ['owner_custom_field_id' => $customField->getKey()],
                    $typedValue,
                );
            }

            $staleCustomFields = $savedVenue->ownerCustomFields();

            if ($keptCustomFieldIds !== []) {
                $staleCustomFields->whereNotIn('id', $keptCustomFieldIds);
            }

            $staleCustomFieldIds = $staleCustomFields->pluck('id')->all();

            if ($staleCustomFieldIds !== []) {
                $savedVenue->fieldValues()->whereIn('owner_custom_field_id', $staleCustomFieldIds)->delete();
                $savedVenue->ownerCustomFields()->whereIn('id', $staleCustomFieldIds)->delete();
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
        $templates = $this->templates;
        $selectedTemplate = $templates->firstWhere('id', ctype_digit($this->venueTemplateId) ? (int) $this->venueTemplateId : 0);
        $selectedCategory = $categories->firstWhere('id', ctype_digit($categoryId) ? (int) $categoryId : 0);
        $facilityOptions = $this->availableFacilities();
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
                    <select id="venue-category" wire:model.live="categoryId" required class="ui-input" aria-invalid="{{ $errors->has('categoryId') ? 'true' : 'false' }}">
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

                @if ($categoryId !== '' && $templates->isNotEmpty())
                    <div>
                        <label for="venue-template" class="ui-label">Template informasi</label>
                        <select id="venue-template" wire:model.live="venueTemplateId" class="ui-input" aria-invalid="{{ $errors->has('venueTemplateId') ? 'true' : 'false' }}">
                            <option value="">Pilih template sesuai kategori</option>
                            @foreach ($templates as $templateOption)
                                <option value="{{ $templateOption->id }}">{{ $templateOption->name }} · v{{ $templateOption->version }}</option>
                            @endforeach
                        </select>
                        @error('venueTemplateId') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                        <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Field tambahan berubah sesuai kategori dan template yang dipilih.</p>
                    </div>
                @elseif ($categoryId !== '')
                    <p class="status-note">Belum ada template aktif untuk kategori ini. Informasi dasar tetap dapat disimpan.</p>
                @endif

                <div>
                    <label for="venue-capacity" class="ui-label">Kapasitas orang</label>
                    <input id="venue-capacity" type="number" min="1" max="100000" step="1" wire:model="capacityPersons" class="ui-input" placeholder="Contoh: 80" aria-invalid="{{ $errors->has('capacityPersons') ? 'true' : 'false' }}">
                    @error('capacityPersons') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Kosongkan jika kapasitas belum diketahui.</p>
                </div>

                <fieldset class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <legend class="px-1 text-sm font-extrabold text-[var(--ink)]">Fasilitas standar</legend>
                    <p class="mb-3 text-sm leading-6 text-[var(--ink-soft)]">Pilih fasilitas yang benar-benar tersedia di venue.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($facilityOptions as $facility)
                            <label class="flex cursor-pointer items-start gap-2 text-sm leading-5 text-[var(--ink-soft)]">
                                <input type="checkbox" wire:model="facilities" value="{{ $facility }}" class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                <span>{{ $facility }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('facilities') <p role="alert" class="ui-error mt-2">{{ $message }}</p> @enderror
                    @error('facilities.*') <p role="alert" class="ui-error mt-2">{{ $message }}</p> @enderror
                </fieldset>

                @if ($selectedTemplate && $selectedTemplate->fields->isNotEmpty())
                    <section aria-labelledby="dynamic-template-fields-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                        <div class="mb-4">
                            <h3 id="dynamic-template-fields-heading" class="text-base font-extrabold text-[var(--ink)]">Detail khusus kategori</h3>
                            <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Lengkapi informasi tambahan untuk {{ $selectedCategory?->name ?? 'kategori ini' }}.</p>
                        </div>

                        <div class="space-y-5">
                            @foreach ($selectedTemplate->fields as $field)
                                @php
                                    $fieldInputId = 'template-field-' . $field->field_key;
                                    $fieldValue = $templateValues[$field->field_key] ?? ($field->input_type === 'boolean' ? false : ($field->input_type === 'multiselect' ? [] : ''));
                                    $fieldOptions = $field->options_json ?? [];
                                    $fieldValidation = $field->validation_rules_json ?? [];
                                @endphp
                                <div wire:key="template-field-{{ $field->id }}">
                                    @if ($field->input_type === 'boolean')
                                        <label for="{{ $fieldInputId }}" class="flex cursor-pointer items-start gap-3 text-sm text-[var(--ink-soft)]">
                                            <input id="{{ $fieldInputId }}" type="checkbox" wire:model="templateValues.{{ $field->field_key }}" class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                            <span>
                                                <span class="block font-bold text-[var(--ink)]">{{ $field->label }}</span>
                                                @if ($field->unit_label)
                                                    <span class="mt-1 block text-xs text-[var(--muted)]">{{ $field->unit_label }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @else
                                        <label for="{{ $fieldInputId }}" class="ui-label">
                                            {{ $field->label }}
                                            @if ($field->is_required)
                                                <span class="text-[var(--danger-ink)]">*</span>
                                            @endif
                                            @if ($field->unit_label)
                                                <span class="font-medium text-[var(--muted)]">({{ $field->unit_label }})</span>
                                            @endif
                                        </label>

                                        @if ($field->input_type === 'text')
                                            <input id="{{ $fieldInputId }}" type="text" wire:model="templateValues.{{ $field->field_key }}" class="ui-input" maxlength="{{ $fieldValidation['max_length'] ?? 2000 }}" placeholder="Isi {{ strtolower($field->label) }}" @if($field->is_required) required @endif aria-invalid="{{ $errors->has('templateValues.' . $field->field_key) ? 'true' : 'false' }}">
                                        @elseif ($field->input_type === 'textarea')
                                            <textarea id="{{ $fieldInputId }}" wire:model="templateValues.{{ $field->field_key }}" rows="3" class="ui-input resize-y" maxlength="{{ $fieldValidation['max_length'] ?? 10000 }}" placeholder="Isi {{ strtolower($field->label) }}" @if($field->is_required) required @endif aria-invalid="{{ $errors->has('templateValues.' . $field->field_key) ? 'true' : 'false' }}"></textarea>
                                        @elseif ($field->input_type === 'number')
                                            <input id="{{ $fieldInputId }}" type="number" step="any" wire:model="templateValues.{{ $field->field_key }}" class="ui-input" @if(isset($fieldValidation['min'])) min="{{ $fieldValidation['min'] }}" @endif @if(isset($fieldValidation['max'])) max="{{ $fieldValidation['max'] }}" @endif placeholder="Masukkan angka" @if($field->is_required) required @endif aria-invalid="{{ $errors->has('templateValues.' . $field->field_key) ? 'true' : 'false' }}">
                                        @elseif ($field->input_type === 'select')
                                            <select id="{{ $fieldInputId }}" wire:model="templateValues.{{ $field->field_key }}" class="ui-input" @if($field->is_required) required @endif aria-invalid="{{ $errors->has('templateValues.' . $field->field_key) ? 'true' : 'false' }}">
                                                <option value="">Pilih {{ strtolower($field->label) }}</option>
                                                @foreach ($fieldOptions as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field->input_type === 'multiselect')
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                @foreach ($fieldOptions as $optionIndex => $option)
                                                    <label for="{{ $fieldInputId }}-{{ $optionIndex }}" class="flex cursor-pointer items-start gap-2 text-sm leading-5 text-[var(--ink-soft)]">
                                                        <input id="{{ $fieldInputId }}-{{ $optionIndex }}" type="checkbox" wire:model="templateValues.{{ $field->field_key }}" value="{{ $option }}" class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                                        <span>{{ $option }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif

                                    @error("templateValues.{$field->field_key}")
                                        <p role="alert" class="ui-error mt-2">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section aria-labelledby="owner-custom-fields-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 id="owner-custom-fields-heading" class="text-base font-extrabold text-[var(--ink)]">Informasi tambahan buatan sendiri</h3>
                            <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Tambahkan detail khusus venue dengan tipe teks, angka, ya/tidak, atau pilihan. Field ini tidak otomatis menjadi filter pencarian.</p>
                        </div>
                        <button type="button" wire:click="addCustomField" class="btn btn-secondary w-full shrink-0 sm:w-auto">Tambah field</button>
                    </div>

                    @error('customFields') <p role="alert" class="ui-error mt-3">{{ $message }}</p> @enderror

                    @if (count($customFields) === 0)
                        <p class="mt-4 rounded-lg bg-[var(--surface-soft)] p-3 text-sm leading-6 text-[var(--muted)]">Belum ada field tambahan. Kamu dapat menambahkannya untuk mencantumkan informasi yang tidak tersedia pada template kategori.</p>
                    @else
                        <div class="mt-4 space-y-4">
                            @foreach ($customFields as $index => $customField)
                                @php
                                    $customFieldOptions = collect(preg_split('/\\r\\n|\\r|\\n/', (string) ($customField['options_text'] ?? '')) ?: [])
                                        ->map(fn (string $option): string => trim($option))
                                        ->filter(fn (string $option): bool => $option !== '')
                                        ->values();
                                    $customFieldInputId = 'owner-custom-field-' . $index;
                                @endphp
                                <article wire:key="owner-custom-field-{{ $index }}" class="rounded-xl border border-[var(--border)] p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--muted)]">Custom field {{ $index + 1 }}</p>
                                            <h4 class="mt-1 text-sm font-extrabold text-[var(--ink)]">{{ $customField['label'] ?: 'Field baru' }}</h4>
                                        </div>
                                        <button type="button" wire:click="removeCustomField({{ $index }})" class="btn btn-secondary min-h-9 px-3 py-1.5" aria-label="Hapus custom field {{ $index + 1 }}">Hapus</button>
                                    </div>

                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="owner-custom-label-{{ $index }}" class="ui-label">Label field</label>
                                            <input id="owner-custom-label-{{ $index }}" type="text" wire:model="customFields.{{ $index }}.label" maxlength="120" required class="ui-input" placeholder="Contoh: Jam maksimal penggunaan">
                                            @error("customFields.{$index}.label") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="owner-custom-type-{{ $index }}" class="ui-label">Tipe input</label>
                                            <select id="owner-custom-type-{{ $index }}" wire:model.live="customFields.{{ $index }}.input_type" class="ui-input" required>
                                                <option value="text">Teks</option>
                                                <option value="number">Angka</option>
                                                <option value="boolean">Ya / Tidak</option>
                                                <option value="select">Satu pilihan</option>
                                            </select>
                                            @error("customFields.{$index}.input_type") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    @if ($customField['input_type'] === 'select')
                                        <div class="mt-4">
                                            <label for="owner-custom-options-{{ $index }}" class="ui-label">Pilihan (satu opsi per baris)</label>
                                            <textarea id="owner-custom-options-{{ $index }}" wire:model="customFields.{{ $index }}.options_text" rows="3" maxlength="4000" class="ui-input resize-y font-mono text-sm" placeholder="Pagi&#10;Siang&#10;Malam"></textarea>
                                            @error("customFields.{$index}.options_text") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                    @endif

                                    <div class="mt-4 border-t border-[var(--border)] pt-4">
                                        <label for="{{ $customFieldInputId }}" class="ui-label">Nilai untuk venue ini</label>
                                        @if ($customField['input_type'] === 'boolean')
                                            <label for="{{ $customFieldInputId }}" class="flex cursor-pointer items-center gap-2 text-sm text-[var(--ink-soft)]">
                                                <input id="{{ $customFieldInputId }}" type="checkbox" wire:model="customFieldValues.{{ $index }}" class="size-4 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                                Ya
                                            </label>
                                        @elseif ($customField['input_type'] === 'number')
                                            <input id="{{ $customFieldInputId }}" type="number" step="any" wire:model="customFieldValues.{{ $index }}" class="ui-input" placeholder="Masukkan angka">
                                        @elseif ($customField['input_type'] === 'select')
                                            <select id="{{ $customFieldInputId }}" wire:model="customFieldValues.{{ $index }}" class="ui-input">
                                                <option value="">Pilih nilai</option>
                                                @foreach ($customFieldOptions as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input id="{{ $customFieldInputId }}" type="text" wire:model="customFieldValues.{{ $index }}" maxlength="2000" class="ui-input" placeholder="Masukkan nilai">
                                        @endif
                                        @error("customFieldValues.{$index}") <p role="alert" class="ui-error mt-2">{{ $message }}</p> @enderror
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section aria-labelledby="venue-media-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div>
                        <h3 id="venue-media-heading" class="text-base font-extrabold text-[var(--ink)]">Foto venue</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Unggah foto JPEG, PNG, atau WebP maksimal 5 MB per file. SVG tidak diperbolehkan. Tambahkan deskripsi foto untuk aksesibilitas.</p>
                    </div>

                    @if (!$isEditing)
                        <p class="status-note mt-4">Simpan venue sebagai draft terlebih dahulu. Setelah venue memiliki ID, kamu dapat mengunggah dan mengatur foto.</p>
                    @else
                        <div class="mt-4 rounded-xl bg-[var(--surface-soft)] p-4">
                            <div>
                                <label for="media-upload" class="ui-label">Pilih foto</label>
                                <input id="media-upload" type="file" wire:model="mediaUpload" accept="image/jpeg,image/png,image/webp" class="ui-input">
                                @error('mediaUpload') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                <div wire:loading wire:target="mediaUpload" class="mt-2 text-xs font-semibold text-[var(--muted)]">Mengunggah file sementara...</div>
                            </div>
                            <div class="mt-3">
                                <label for="media-alt-text" class="ui-label">Deskripsi singkat foto (alt text)</label>
                                <input id="media-alt-text" type="text" wire:model="mediaAltText" maxlength="180" required class="ui-input" placeholder="Contoh: Tampak depan ruang acara dengan kursi tersusun">
                                @error('mediaAltText') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <label class="mt-3 flex cursor-pointer items-start gap-2 text-sm leading-5 text-[var(--ink-soft)]">
                                <input type="checkbox" wire:model="mediaIsCover" class="mt-0.5 size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                <span>Jadikan foto sampul</span>
                            </label>
                            <button type="button" wire:click="uploadMedia" wire:loading.attr="disabled" class="btn btn-primary mt-4 w-full sm:w-auto">
                                <span wire:loading.remove wire:target="uploadMedia">Unggah foto</span>
                                <span wire:loading wire:target="uploadMedia">Menyimpan foto...</span>
                            </button>
                        </div>

                        @if ($venue->media->isEmpty())
                            <p class="mt-4 rounded-lg border border-dashed border-[var(--border-strong)] p-4 text-sm leading-6 text-[var(--muted)]">Belum ada foto venue. Foto pertama otomatis menjadi foto sampul.</p>
                        @else
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach ($venue->media as $mediaItem)
                                    <article wire:key="venue-media-{{ $mediaItem->id }}" class="min-w-0 overflow-hidden rounded-xl border border-[var(--border)] bg-white">
                                        <div class="relative aspect-[4/3] bg-[var(--surface-soft)]">
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($mediaItem->disk)->url($mediaItem->file_path) }}" alt="{{ $mediaItem->alt_text }}" loading="lazy" decoding="async" class="size-full object-cover">
                                            @if ($mediaItem->is_cover)
                                                <span class="absolute left-3 top-3 rounded-md bg-[var(--brand-forest)] px-2.5 py-1 text-xs font-bold text-white">Foto sampul</span>
                                            @endif
                                        </div>
                                        <div class="space-y-3 p-4">
                                            <p class="text-xs text-[var(--muted)]">{{ strtoupper(str_replace('image/', '', $mediaItem->mime_type)) }} · {{ number_format($mediaItem->file_size_bytes / 1048576, 2) }} MB</p>
                                            <div>
                                                <label for="media-alt-{{ $mediaItem->id }}" class="ui-label">Deskripsi foto</label>
                                                <input id="media-alt-{{ $mediaItem->id }}" type="text" wire:model="mediaAltTexts.{{ $mediaItem->id }}" maxlength="180" class="ui-input">
                                                @error("mediaAltTexts.{$mediaItem->id}") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                                <button type="button" wire:click="updateMediaAltText({{ $mediaItem->id }})" class="btn btn-secondary mt-2 w-full sm:w-auto">Simpan deskripsi</button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                @if (!$mediaItem->is_cover)
                                                    <button type="button" wire:click="setMediaCover({{ $mediaItem->id }})" class="btn btn-secondary w-full">Jadikan sampul</button>
                                                @else
                                                    <span class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[var(--surface-soft)] px-3 text-center text-xs font-bold text-[var(--ink-soft)]">Sampul aktif</span>
                                                @endif
                                                <button type="button" wire:click="deleteMedia({{ $mediaItem->id }})" wire:confirm="Hapus foto ini dari venue?" class="btn btn-secondary w-full">Hapus foto</button>
                                            </div>
                                            <div class="flex gap-2">
                                                @if (!$loop->first)
                                                    <button type="button" wire:click="moveMedia({{ $mediaItem->id }}, 'up')" class="btn btn-secondary min-h-9 flex-1 px-3 py-1.5">Geser ke kiri/atas</button>
                                                @endif
                                                @if (!$loop->last)
                                                    <button type="button" wire:click="moveMedia({{ $mediaItem->id }}, 'down')" class="btn btn-secondary min-h-9 flex-1 px-3 py-1.5">Geser ke kanan/bawah</button>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </section>

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