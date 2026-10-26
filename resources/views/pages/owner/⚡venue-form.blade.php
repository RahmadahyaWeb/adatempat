<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateField;
use App\Models\OwnerCustomField;
use App\Models\OwnerCustomField;
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

    public array $customFields = [];

    public array $customFieldValues = [];

    public array $ownerCustomFields = [];

    public array $ownerCustomValues = [];

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
                ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields.values'])
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
            $this->loadCustomFieldsFromVenue($record);
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
            ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields'])
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
        foreach ($venue->ownerCustomFields->where('moderation_status', OwnerCustomField::STATUS_ACTIVE)->sortBy('sort_order')->values() as $field) {
            $value = $field->values->first();

            $this->ownerCustomFields[] = [
                'id' => (string) $field->getKey(),
                'field_key' => $field->field_key,
                'label' => $field->label,
                'input_type' => $field->input_type,
                'options_text' => implode("\n", $field->options_json ?? []),
                'sort_order' => $field->sort_order,
                'has_values' => $field->values->isNotEmpty(),
            ];

            $this->ownerCustomValues[$field->field_key] = match ($field->input_type) {
                OwnerCustomField::TYPE_BOOLEAN => (bool) ($value?->value_boolean ?? false),
                OwnerCustomField::TYPE_NUMBER => $value?->value_number !== null ? (string) $value->value_number : '',
                default => (string) ($value?->value_text ?? ''),
            };
        }
    }

    private function blankOwnerCustomField(int $sortOrder): array
    {
        return [
            'id' => '',
            'field_key' => '',
            'label' => '',
            'input_type' => OwnerCustomField::TYPE_TEXT,
            'options_text' => '',
            'sort_order' => $sortOrder,
            'has_values' => false,
        ];
    }

    public function addOwnerCustomField(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $this->ownerCustomFields[] = $this->blankOwnerCustomField((count($this->ownerCustomFields) + 1) * 10);
    }

    public function removeOwnerCustomField(int $index): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        $field = $this->ownerCustomFields[$index] ?? null;

        if (! is_array($field)) {
            return;
        }

        $fieldId = trim((string) ($field['id'] ?? ''));

        if ($fieldId !== '') {
            $venue = $this->currentVenue;

            abort_unless($venue !== null, 404);

            $record = $venue->ownerCustomFields()
                ->whereKey((int) $fieldId)
                ->where('moderation_status', OwnerCustomField::STATUS_ACTIVE)
                ->firstOrFail();

            if ($record->values()->exists()) {
                $this->addError('ownerCustomFields', 'Field ini sudah memiliki data venue dan tidak dapat dihapus. Ubah label atau opsinya, atau biarkan field tetap tersedia.');

                return;
            }
        }

        unset($this->ownerCustomFields[$index]);

        $this->ownerCustomFields = array_values($this->ownerCustomFields);
        $this->resetValidation('ownerCustomFields');
    }

    public function updatedOwnerCustomFields(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        $parts = explode('.', $key);

        if (count($parts) < 2 || $parts[1] !== 'input_type') {
            return;
        }

        $field = $this->ownerCustomFields[(int) $parts[0]] ?? null;

        if (! is_array($field) || ! isset($field['field_key'])) {
            return;
        }

        $this->ownerCustomValues[$field['field_key']] = match ($field['input_type']) {
            OwnerCustomField::TYPE_BOOLEAN => false,
            default => '',
        };
    }

    private function loadCustomFieldsFromVenue(Venue $venue): void
    {
        $this->customFields = [];
        $this->customFieldValues = [];

        foreach ($venue->ownerCustomFields as $field) {
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
                'customFields.*.field_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
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
                'customFields.*.field_key.regex' => 'Key custom field tidak valid.',
                'customFields.*.options_text.max' => 'Daftar pilihan terlalu panjang.',
            ],
        );

        $existingCustomFieldIds = $venue?->ownerCustomFields()->pluck('id')->all() ?? [];

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
                'field_key' => (string) ($field['field_key'] ?? ''),
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