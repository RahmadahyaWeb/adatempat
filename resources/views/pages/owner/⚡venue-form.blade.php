<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateField;
use App\Models\OwnerCustomField;
use App\Models\VenuePrice;
use App\Services\Booking\VenueBlockService;
use Carbon\CarbonImmutable;
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

    public array $availabilityRules = [];

    public string $pricingUnit = '';

    public array $priceRows = [];

    public string $blockStartsAt = '';

    public string $blockEndsAt = '';

    public string $blockReason = '';

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
        $this->availabilityRules = $this->defaultAvailabilityRules();
        $this->priceRows = [];

        if ($venue !== null) {
            $record = $businessRecord->venues()
                ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields', 'media', 'availabilityRules', 'prices', 'blocks'])
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

            foreach ($record->availabilityRules as $rule) {
                $this->availabilityRules[(int) $rule->day_of_week] = [
                    'day_of_week' => (int) $rule->day_of_week,
                    'is_open' => (bool) $rule->is_open,
                    'open_time' => substr((string) $rule->open_time, 0, 5),
                    'close_time' => substr((string) $rule->close_time, 0, 5),
                    'buffer_minutes' => (string) $rule->buffer_minutes,
                ];
            }
            ksort($this->availabilityRules);

            $activePrices = $record->prices->where('is_active', true)->sortBy('id')->values();
            $this->pricingUnit = (string) ($activePrices->first()?->pricing_unit ?? '');
            $this->priceRows = $activePrices->map(fn ($price) => [
                'id' => $price->getKey(),
                'session_name' => (string) ($price->session_name ?? ''),
                'session_start_time' => $price->session_start_time !== null ? substr((string) $price->session_start_time, 0, 5) : '09:00',
                'session_end_time' => $price->session_end_time !== null ? substr((string) $price->session_end_time, 0, 5) : '17:00',
                'step_interval_minutes' => (string) $price->step_interval_minutes,
                'min_duration_units' => (string) $price->min_duration_units,
                'max_duration_units' => (string) $price->max_duration_units,
                'price_amount' => (string) $price->price_amount,
                'weekend_price_amount' => $price->weekend_price_amount !== null ? (string) $price->weekend_price_amount : '',
            ])->all();
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
            ->with(['category', 'venueTemplate.fields', 'fieldValues.templateField', 'fieldValues.ownerCustomField', 'ownerCustomFields', 'media', 'availabilityRules', 'prices'])
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
            'mediaUpload' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mediaAltText' => ['required', 'string', 'max:180'],
            'mediaIsCover' => ['boolean'],
        ], [
            'mediaUpload.required' => 'Pilih foto yang akan diunggah.',
            'mediaUpload.image' => 'File harus berupa gambar yang valid.',
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

        if (! is_string($path) || $path === '') {
            $this->addError('mediaUpload', 'Foto gagal disimpan. Silakan coba lagi.');

            return;
        }

        $createdMediaId = null;

        try {
            DB::transaction(function () use ($venue, $file, $mimeType, $disk, $path, $validated, &$createdMediaId): void {
                $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
                $isCover = (bool) $validated['mediaIsCover'] || ! $lockedVenue->media()->exists();

                if ($isCover) {
                    $lockedVenue->media()->update(['is_cover' => false]);
                }

                $sortOrder = ((int) $lockedVenue->media()->max('sort_order')) + 10;

                $createdMedia = $lockedVenue->media()->create([
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
            $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $lockedMedia = $lockedVenue->media()->whereKey($media->getKey())->firstOrFail();
            $lockedVenue->media()->update(['is_cover' => false]);
            $lockedMedia->update(['is_cover' => true]);
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

        DB::transaction(function () use ($venue, $mediaId, $direction): void {
            $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $items = $lockedVenue->media()->orderBy('sort_order')->orderBy('id')->get()->all();
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

            foreach ($items as $itemIndex => $item) {
                $item->update(['sort_order' => ($itemIndex + 1) * 10]);
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
        DB::transaction(function () use ($venue, $mediaId): void {
            $lockedVenue = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $lockedMedia = $lockedVenue->media()->whereKey($mediaId)->firstOrFail();
            $wasCover = $lockedMedia->is_cover;
            $lockedMedia->delete();

            if ($wasCover) {
                $replacement = $lockedVenue->media()->orderBy('sort_order')->orderBy('id')->first();
                $replacement?->update(['is_cover' => true]);
            }
        });

        Storage::disk($disk)->delete($path);
        unset($this->currentVenue, $this->mediaAltTexts[$mediaId]);
        session()->flash('status', 'Foto venue berhasil dihapus.');
    }

    public function createVenueBlock(VenueBlockService $venueBlockService): void
    {
        $owner = auth()->user();

        abort_unless($owner && $owner->isOwner(), 403);

        $venue = $this->currentVenue;

        if ($venue === null) {
            $this->addError('blockStartsAt', 'Simpan venue terlebih dahulu sebelum membuat blokir jadwal.');

            return;
        }

        Gate::authorize('update', $venue);

        $validated = $this->validate([
            'blockStartsAt' => ['required', 'date_format:Y-m-d\\TH:i'],
            'blockEndsAt' => ['required', 'date_format:Y-m-d\\TH:i', 'after:blockStartsAt'],
            'blockReason' => ['required', 'string', 'max:255'],
        ], [
            'blockStartsAt.required' => 'Waktu mulai blokir wajib diisi.',
            'blockStartsAt.date_format' => 'Format waktu mulai tidak valid.',
            'blockEndsAt.required' => 'Waktu selesai blokir wajib diisi.',
            'blockEndsAt.date_format' => 'Format waktu selesai tidak valid.',
            'blockEndsAt.after' => 'Waktu selesai harus lebih akhir daripada waktu mulai.',
            'blockReason.required' => 'Alasan blokir wajib diisi.',
            'blockReason.max' => 'Alasan blokir maksimal 255 karakter.',
        ]);

        $timezone = $this->currentBusiness->timezone ?: 'Asia/Jakarta';
        $startsAtUtc = CarbonImmutable::parse($validated['blockStartsAt'], $timezone)->utc();
        $endsAtUtc = CarbonImmutable::parse($validated['blockEndsAt'], $timezone)->utc();

        $venueBlockService->create(
            $owner,
            $venue,
            $startsAtUtc,
            $endsAtUtc,
            $validated['blockReason'],
        );

        $this->reset(['blockStartsAt', 'blockEndsAt', 'blockReason']);
        $this->resetValidation(['blockStartsAt', 'blockEndsAt', 'blockReason']);
        unset($this->currentVenue);
        session()->flash('status', 'Blokir jadwal berhasil disimpan.');
    }

    public function deleteVenueBlock(int $blockId, VenueBlockService $venueBlockService): void
    {
        $owner = auth()->user();

        abort_unless($owner && $owner->isOwner(), 403);

        $venue = $this->currentVenue;
        abort_unless($venue !== null, 404);
        Gate::authorize('update', $venue);

        $venueBlockService->delete($venue, $blockId);

        unset($this->currentVenue);
        session()->flash('status', 'Blokir jadwal berhasil dihapus.');
    }

    private function defaultAvailabilityRules(): array
    {
        $rules = [];

        for ($day = 0; $day <= 6; $day++) {
            $rules[$day] = [
                'day_of_week' => $day,
                'is_open' => false,
                'open_time' => '09:00',
                'close_time' => '17:00',
                'buffer_minutes' => '0',
            ];
        }

        return $rules;
    }

    private function blankPriceRow(): array
    {
        return [
            'id' => null,
            'session_name' => '',
            'session_start_time' => '09:00',
            'session_end_time' => '17:00',
            'step_interval_minutes' => '60',
            'min_duration_units' => '1',
            'max_duration_units' => '24',
            'price_amount' => '',
            'weekend_price_amount' => '',
        ];
    }

    public function updatedPricingUnit(): void
    {
        $this->resetValidation('pricingUnit');
        $this->resetValidation('priceRows');

        if ($this->pricingUnit === '') {
            $this->priceRows = [];

            return;
        }

        if ($this->pricingUnit === VenuePrice::UNIT_SESSION) {
            if ($this->priceRows === [] || trim((string) ($this->priceRows[0]['session_name'] ?? '')) === '') {
                $this->priceRows = [$this->blankPriceRow()];
            }

            return;
        }

        $this->priceRows = [$this->blankPriceRow()];
    }

    public function addSessionPrice(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        if ($this->pricingUnit !== VenuePrice::UNIT_SESSION) {
            return;
        }

        if (count($this->priceRows) >= 50) {
            $this->addError('priceRows', 'Maksimal 50 sesi harga per venue.');

            return;
        }

        $this->priceRows[] = $this->blankPriceRow();
    }

    public function removePriceRow(int $index): void
    {
        abort_unless(auth()->user()?->isOwner(), 403);

        if ($this->pricingUnit !== VenuePrice::UNIT_SESSION || ! array_key_exists($index, $this->priceRows)) {
            return;
        }

        if (count($this->priceRows) <= 1) {
            $this->addError('priceRows', 'Model harga sesi harus memiliki setidaknya satu sesi.');

            return;
        }

        unset($this->priceRows[$index]);
        $this->priceRows = array_values($this->priceRows);
        $this->resetValidation('priceRows');
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

        $this->availabilityRules = array_values($this->availabilityRules);
        $this->priceRows = array_values($this->priceRows);

        foreach ($this->priceRows as $index => $priceRow) {
            if (trim((string) ($priceRow['weekend_price_amount'] ?? '')) === '') {
                $this->priceRows[$index]['weekend_price_amount'] = null;
            }
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
                'customFields.*.field_key' => ['nullable', 'string', 'max:80'],
                'customFields.*.label' => ['required', 'string', 'max:120'],
                'customFields.*.input_type' => ['required', Rule::in([OwnerCustomField::TYPE_TEXT, OwnerCustomField::TYPE_NUMBER, OwnerCustomField::TYPE_BOOLEAN, OwnerCustomField::TYPE_SELECT])],
                'customFields.*.options_text' => ['nullable', 'string', 'max:4000'],
                'customFields.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
                'customFieldValues' => ['array'],
                'customFieldValues.*' => ['nullable'],
                'availabilityRules' => ['required', 'array', 'size:7'],
                'availabilityRules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6', 'distinct'],
                'availabilityRules.*.is_open' => ['required', 'boolean'],
                'availabilityRules.*.open_time' => ['required', 'date_format:H:i'],
                'availabilityRules.*.close_time' => ['required', 'date_format:H:i'],
                'availabilityRules.*.buffer_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'pricingUnit' => ['nullable', 'string'],
                'priceRows' => ['array', 'max:50'],
                'priceRows.*.id' => ['nullable', 'integer'],
                'priceRows.*.session_name' => ['nullable', 'string', 'max:80'],
                'priceRows.*.session_start_time' => ['nullable', 'date_format:H:i'],
                'priceRows.*.session_end_time' => ['nullable', 'date_format:H:i'],
                'priceRows.*.step_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'priceRows.*.min_duration_units' => ['required', 'integer', 'min:1', 'max:65535'],
                'priceRows.*.max_duration_units' => ['required', 'integer', 'min:1', 'max:65535'],
                'priceRows.*.price_amount' => ['required', 'integer', 'min:0'],
                'priceRows.*.weekend_price_amount' => ['nullable', 'integer', 'min:0'],
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
                'availabilityRules.size' => 'Jadwal harus memuat seluruh tujuh hari dalam seminggu.',
                'availabilityRules.*.day_of_week.distinct' => 'Setiap hari hanya boleh memiliki satu jadwal.',
                'availabilityRules.*.open_time.date_format' => 'Format jam buka tidak valid.',
                'availabilityRules.*.close_time.date_format' => 'Format jam tutup tidak valid.',
                'availabilityRules.*.buffer_minutes.integer' => 'Waktu jeda harus berupa menit.',
                'availabilityRules.*.buffer_minutes.min' => 'Waktu jeda tidak boleh negatif.',
                'availabilityRules.*.buffer_minutes.max' => 'Waktu jeda maksimal 1.440 menit.',
                'priceRows.max' => 'Maksimal 50 baris harga per venue.',
                'priceRows.*.session_name.max' => 'Nama sesi maksimal 80 karakter.',
                'priceRows.*.price_amount.required' => 'Harga wajib diisi.',
                'priceRows.*.price_amount.integer' => 'Harga harus berupa angka Rupiah bulat.',
                'priceRows.*.price_amount.min' => 'Harga tidak boleh negatif.',
                'priceRows.*.weekend_price_amount.integer' => 'Harga akhir pekan harus berupa angka Rupiah bulat.',
                'priceRows.*.weekend_price_amount.min' => 'Harga akhir pekan tidak boleh negatif.',
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

        $existingPriceIds = array_map('intval', $venue?->prices()->pluck('id')->all() ?? []);

        foreach ($validated['priceRows'] ?? [] as $index => $priceRow) {
            $priceId = $priceRow['id'] ?? null;

            if ($priceId !== null && ! in_array((int) $priceId, $existingPriceIds, true)) {
                $this->addError("priceRows.{$index}.id", 'Baris harga tidak terkait dengan venue ini.');

                return;
            }
        }

        if (! in_array($validated['pricingUnit'] ?? '', ['', VenuePrice::UNIT_HOURLY, VenuePrice::UNIT_SESSION, VenuePrice::UNIT_DAILY], true)) {
            $this->addError('pricingUnit', 'Pilih model harga yang didukung.');

            return;
        }

        foreach ($validated['availabilityRules'] as $index => $rule) {
            if ((bool) $rule['is_open'] && $rule['open_time'] === $rule['close_time']) {
                $this->addError("availabilityRules.{$index}.close_time", 'Jam tutup tidak boleh sama dengan jam buka. Gunakan jam tutup yang lebih awal untuk operasional lintas tengah malam.');

                return;
            }
        }

        $pricingUnit = (string) ($validated['pricingUnit'] ?? '');

        if ($pricingUnit !== '' && ($validated['priceRows'] ?? []) === []) {
            $this->addError('priceRows', 'Tambahkan setidaknya satu baris harga.');

            return;
        }

        if ($pricingUnit !== VenuePrice::UNIT_SESSION && $pricingUnit !== '' && count($validated['priceRows'] ?? []) !== 1) {
            $this->addError('priceRows', 'Model harga per jam atau per hari harus memiliki tepat satu konfigurasi harga.');

            return;
        }

        $priceRowRules = [];
        $priceRowMessages = [];

        if ($pricingUnit === VenuePrice::UNIT_SESSION) {
            foreach ($validated['priceRows'] as $index => $priceRow) {
                $priceRowRules["priceRows.{$index}.session_name"] = ['required', 'string', 'max:80'];
                $priceRowRules["priceRows.{$index}.session_start_time"] = ['required', 'date_format:H:i'];
                $priceRowRules["priceRows.{$index}.session_end_time"] = ['required', 'date_format:H:i'];
                $priceRowMessages["priceRows.{$index}.session_name.required"] = 'Nama sesi wajib diisi.';
                $priceRowMessages["priceRows.{$index}.session_start_time.required"] = 'Jam mulai sesi wajib diisi.';
                $priceRowMessages["priceRows.{$index}.session_end_time.required"] = 'Jam selesai sesi wajib diisi.';

                if (($priceRow['session_start_time'] ?? null) === ($priceRow['session_end_time'] ?? null)) {
                    $this->addError("priceRows.{$index}.session_end_time", 'Jam selesai sesi tidak boleh sama dengan jam mulai.');

                    return;
                }
            }
        }

        if ($priceRowRules !== []) {
            $this->validate($priceRowRules, $priceRowMessages);
        }

        foreach ($validated['priceRows'] ?? [] as $index => $priceRow) {
            if ((int) $priceRow['max_duration_units'] < (int) $priceRow['min_duration_units']) {
                $this->addError("priceRows.{$index}.max_duration_units", 'Durasi maksimum harus lebih besar atau sama dengan durasi minimum.');

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

        $normalisedAvailabilityRules = collect($validated['availabilityRules'])
            ->sortBy('day_of_week')
            ->map(fn (array $rule): array => [
                'day_of_week' => (int) $rule['day_of_week'],
                'is_open' => (bool) $rule['is_open'],
                'open_time' => $rule['open_time'],
                'close_time' => $rule['close_time'],
                'buffer_minutes' => (int) $rule['buffer_minutes'],
            ])
            ->values()
            ->all();

        $normalisedPriceRows = [];

        if ($pricingUnit !== '') {
            foreach ($validated['priceRows'] as $priceRow) {
                $normalisedPriceRows[] = [
                    'id' => isset($priceRow['id']) && $priceRow['id'] !== '' ? (int) $priceRow['id'] : null,
                    'pricing_unit' => $pricingUnit,
                    'session_name' => $pricingUnit === VenuePrice::UNIT_SESSION ? trim($priceRow['session_name']) : null,
                    'session_start_time' => $pricingUnit === VenuePrice::UNIT_SESSION ? $priceRow['session_start_time'] : null,
                    'session_end_time' => $pricingUnit === VenuePrice::UNIT_SESSION ? $priceRow['session_end_time'] : null,
                    'step_interval_minutes' => (int) $priceRow['step_interval_minutes'],
                    'min_duration_units' => (int) $priceRow['min_duration_units'],
                    'max_duration_units' => (int) $priceRow['max_duration_units'],
                    'price_amount' => (int) $priceRow['price_amount'],
                    'weekend_price_amount' => trim((string) ($priceRow['weekend_price_amount'] ?? '')) !== ''
                        ? (int) $priceRow['weekend_price_amount']
                        : null,
                ];
            }
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

        $savedVenue = DB::transaction(function () use ($business, $venue, $attributes, $template, $normalisedCustomFields, $normalisedCustomValues, $normalisedAvailabilityRules, $normalisedPriceRows, $pricingUnit): Venue {
            if ($venue === null) {
                $savedVenue = $business->venues()->create($attributes + [
                    'status' => Venue::STATUS_DRAFT,
                    'published_at' => null,
                ]);
            } else {
                $savedVenue = $business->venues()
                    ->whereKey($venue->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $savedVenue->fill($attributes)->save();
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

            foreach ($normalisedAvailabilityRules as $rule) {
                $savedVenue->availabilityRules()->updateOrCreate(
                    ['day_of_week' => $rule['day_of_week']],
                    [
                        'is_open' => $rule['is_open'],
                        'open_time' => $rule['open_time'],
                        'close_time' => $rule['close_time'],
                        'buffer_minutes' => $rule['buffer_minutes'],
                    ],
                );
            }

            $activePrices = $savedVenue->prices()->where('is_active', true)->orderBy('id')->get();

            if ($pricingUnit === '') {
                $savedVenue->prices()->where('is_active', true)->update(['is_active' => false]);
            } else {
                $activeUnit = $activePrices->first()?->pricing_unit;

                if ($activeUnit !== $pricingUnit) {
                    $savedVenue->prices()->where('is_active', true)->update(['is_active' => false]);
                    $activePrices = collect();
                }

                $keptPriceIds = [];

                foreach ($normalisedPriceRows as $priceData) {
                    $priceId = $priceData['id'];
                    unset($priceData['id']);

                    $existingPrice = $priceId !== null
                        ? $activePrices->firstWhere('id', $priceId)
                        : null;

                    if ($existingPrice !== null) {
                        $existingPrice->fill($priceData + ['is_active' => true])->save();
                        $savedPrice = $existingPrice;
                    } else {
                        $savedPrice = $savedVenue->prices()->create($priceData + ['is_active' => true]);
                    }

                    $keptPriceIds[] = $savedPrice->getKey();
                }

                $stalePrices = $savedVenue->prices()->where('is_active', true);

                if ($keptPriceIds !== []) {
                    $stalePrices->whereNotIn('id', $keptPriceIds);
                }

                $stalePrices->update(['is_active' => false]);
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

                <section aria-labelledby="venue-hours-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div>
                        <h3 id="venue-hours-heading" class="text-base font-extrabold text-[var(--ink)]">Jam operasional mingguan</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Atur jam buka per hari. Jam menggunakan zona waktu usaha ({{ $business->timezone ?? 'Asia/Jakarta' }}). Jam tutup lebih awal daripada jam buka berarti operasional berakhir keesokan hari; jam buka dan tutup tidak boleh sama.</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        @php($dayLabels = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'])
                        @foreach ($availabilityRules as $day => $rule)
                            <article wire:key="availability-day-{{ $day }}" class="rounded-lg border border-[var(--border)] p-3 sm:p-4">
                                <label class="flex cursor-pointer items-center gap-3">
                                    <input type="checkbox" wire:model="availabilityRules.{{ $day }}.is_open" class="size-4 shrink-0 rounded border-[var(--border-strong)] accent-[var(--brand-forest)]">
                                    <span class="font-bold text-sm text-[var(--ink)]">{{ $dayLabels[$day] }}</span>
                                    <span class="ml-auto text-xs font-semibold {{ $rule['is_open'] ? 'text-[var(--success-ink)]' : 'text-[var(--muted)]' }}">{{ $rule['is_open'] ? 'Buka' : 'Tutup' }}</span>
                                </label>
                                @if ($rule['is_open'])
                                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <label for="hours-open-{{ $day }}" class="ui-label">Jam buka</label>
                                            <input id="hours-open-{{ $day }}" type="time" wire:model="availabilityRules.{{ $day }}.open_time" required class="ui-input">
                                            @error("availabilityRules.{$day}.open_time") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="hours-close-{{ $day }}" class="ui-label">Jam tutup</label>
                                            <input id="hours-close-{{ $day }}" type="time" wire:model="availabilityRules.{{ $day }}.close_time" required class="ui-input">
                                            @error("availabilityRules.{$day}.close_time") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="hours-buffer-{{ $day }}" class="ui-label">Jeda persiapan (menit)</label>
                                            <input id="hours-buffer-{{ $day }}" type="number" min="0" max="1440" step="1" wire:model="availabilityRules.{{ $day }}.buffer_minutes" required class="ui-input">
                                            @error("availabilityRules.{$day}.buffer_minutes") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-2 text-xs leading-5 text-[var(--muted)]">Venue tidak menerima jadwal baru pada hari ini.</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    @error('availabilityRules') <p role="alert" class="ui-error mt-3">{{ $message }}</p> @enderror
                </section>

                <section aria-labelledby="venue-blocks-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div>
                        <h3 id="venue-blocks-heading" class="text-base font-extrabold text-[var(--ink)]">Blokir jadwal</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Tutup venue untuk perbaikan, acara internal, atau alasan operasional lain. Waktu yang dimasukkan mengikuti zona waktu usaha ({{ $business->timezone ?: 'Asia/Jakarta' }}). Blokir ini akan digunakan oleh availability engine pada tahap booking.</p>
                    </div>

                    @if (!$isEditing)
                        <p class="status-note mt-4">Simpan venue sebagai draft terlebih dahulu sebelum menambahkan blokir jadwal.</p>
                    @else
                        <div class="mt-4 rounded-xl bg-[var(--surface-soft)] p-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="block-starts-at" class="ui-label">Mulai blokir</label>
                                    <input id="block-starts-at" type="datetime-local" wire:model="blockStartsAt" required class="ui-input">
                                    @error('blockStartsAt') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="block-ends-at" class="ui-label">Selesai blokir</label>
                                    <input id="block-ends-at" type="datetime-local" wire:model="blockEndsAt" required class="ui-input">
                                    @error('blockEndsAt') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="block-reason" class="ui-label">Alasan blokir</label>
                                    <input id="block-reason" type="text" wire:model="blockReason" maxlength="255" required class="ui-input" placeholder="Contoh: Perawatan AC dan pengecatan ruangan">
                                    @error('blockReason') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button type="button" wire:click="createVenueBlock" wire:loading.attr="disabled" class="btn btn-secondary mt-4 w-full sm:w-auto">
                                <span wire:loading.remove wire:target="createVenueBlock">Tambah blokir jadwal</span>
                                <span wire:loading wire:target="createVenueBlock">Menyimpan blokir...</span>
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            @forelse ($venue->blocks as $venueBlock)
                                @php
                                    $blockStartLocal = $venueBlock->starts_at->copy()->setTimezone($business->timezone ?: 'Asia/Jakarta');
                                    $blockEndLocal = $venueBlock->ends_at->copy()->setTimezone($business->timezone ?: 'Asia/Jakarta');
                                @endphp
                                <article wire:key="venue-block-{{ $venueBlock->id }}" class="flex flex-col gap-3 rounded-lg border border-[var(--border)] p-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-extrabold text-[var(--ink)]">{{ $venueBlock->reason }}</p>
                                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">{{ $blockStartLocal->format('d/m/Y H:i') }} – {{ $blockEndLocal->format('d/m/Y H:i') }}</p>
                                        <p class="mt-1 text-xs text-[var(--muted)]">{{ $business->timezone ?: 'Asia/Jakarta' }}</p>
                                    </div>
                                    <button type="button" wire:click="deleteVenueBlock({{ $venueBlock->id }})" wire:confirm="Hapus blokir jadwal ini?" class="btn btn-secondary w-full shrink-0 sm:w-auto">Hapus blokir</button>
                                </article>
                            @empty
                                <p class="rounded-lg border border-dashed border-[var(--border-strong)] p-4 text-sm leading-6 text-[var(--muted)]">Belum ada blokir jadwal untuk venue ini.</p>
                            @endforelse
                        </div>
                    @endif
                </section>

                <section aria-labelledby="venue-pricing-heading" class="rounded-xl border border-[var(--border)] p-4 sm:p-5">
                    <div>
                        <h3 id="venue-pricing-heading" class="text-base font-extrabold text-[var(--ink)]">Harga venue</h3>
                        <p class="mt-1 text-sm leading-6 text-[var(--ink-soft)]">Pilih satu model harga aktif. Harga disimpan dalam Rupiah penuh. Untuk harga per jam, interval mengatur kelipatan waktu pemesanan, sedangkan tarif tetap dihitung per jam. Mengubah konfigurasi tidak mengubah snapshot harga booking yang sudah dibuat.</p>
                    </div>

                    <div class="mt-4">
                        <label for="pricing-unit" class="ui-label">Model harga</label>
                        <select id="pricing-unit" wire:model.live="pricingUnit" class="ui-input">
                            <option value="">Belum diatur</option>
                            <option value="hourly">Per jam</option>
                            <option value="session">Per sesi tetap</option>
                            <option value="daily">Per hari</option>
                        </select>
                        @error('pricingUnit') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($pricingUnit === '')
                        <p class="mt-3 rounded-lg bg-[var(--surface-soft)] p-3 text-sm leading-6 text-[var(--muted)]">Harga belum diatur. Venue tetap dapat disimpan sebagai draft, tetapi alur booking belum dapat menggunakan harga sampai konfigurasi tersedia.</p>
                    @elseif ($pricingUnit === 'session')
                        <div class="mt-4 space-y-4">
                            @foreach ($priceRows as $index => $priceRow)
                                <article wire:key="venue-price-session-{{ $index }}" class="rounded-lg border border-[var(--border)] p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-extrabold text-[var(--ink)]">Sesi {{ $index + 1 }}</h4>
                                        @if (count($priceRows) > 1)
                                            <button type="button" wire:click="removePriceRow({{ $index }})" class="btn btn-secondary min-h-9 px-3 py-1.5">Hapus sesi</button>
                                        @endif
                                    </div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="price-session-name-{{ $index }}" class="ui-label">Nama sesi</label>
                                            <input id="price-session-name-{{ $index }}" type="text" wire:model="priceRows.{{ $index }}.session_name" maxlength="80" required class="ui-input" placeholder="Contoh: Sesi pagi">
                                            @error("priceRows.{$index}.session_name") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="price-session-amount-{{ $index }}" class="ui-label">Harga sesi (Rp)</label>
                                            <input id="price-session-amount-{{ $index }}" type="number" min="0" step="1" wire:model="priceRows.{{ $index }}.price_amount" required class="ui-input" placeholder="Contoh: 500000">
                                            @error("priceRows.{$index}.price_amount") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="price-session-start-{{ $index }}" class="ui-label">Jam mulai</label>
                                            <input id="price-session-start-{{ $index }}" type="time" wire:model="priceRows.{{ $index }}.session_start_time" required class="ui-input">
                                            @error("priceRows.{$index}.session_start_time") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="price-session-end-{{ $index }}" class="ui-label">Jam selesai</label>
                                            <input id="price-session-end-{{ $index }}" type="time" wire:model="priceRows.{{ $index }}.session_end_time" required class="ui-input">
                                            @error("priceRows.{$index}.session_end_time") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="price-session-weekend-{{ $index }}" class="ui-label">Harga akhir pekan (Rp, opsional)</label>
                                            <input id="price-session-weekend-{{ $index }}" type="number" min="0" step="1" wire:model="priceRows.{{ $index }}.weekend_price_amount" class="ui-input" placeholder="Kosongkan jika sama dengan harga dasar">
                                            @error("priceRows.{$index}.weekend_price_amount") <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                            @error('priceRows') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            <button type="button" wire:click="addSessionPrice" class="btn btn-secondary w-full sm:w-auto">Tambah sesi</button>
                        </div>
                    @else
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="price-amount" class="ui-label">{{ $pricingUnit === 'hourly' ? 'Harga per jam (Rp)' : 'Harga per hari (Rp)' }}</label>
                                <input id="price-amount" type="number" min="0" step="1" wire:model="priceRows.0.price_amount" required class="ui-input" placeholder="Contoh: 250000">
                                @error('priceRows.0.price_amount') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="price-weekend-amount" class="ui-label">Harga akhir pekan (Rp, opsional)</label>
                                <input id="price-weekend-amount" type="number" min="0" step="1" wire:model="priceRows.0.weekend_price_amount" class="ui-input" placeholder="Kosongkan jika sama dengan harga dasar">
                                @error('priceRows.0.weekend_price_amount') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            @if ($pricingUnit === 'hourly')
                                <div>
                                    <label for="price-step-interval" class="ui-label">Interval pemesanan (menit)</label>
                                    <input id="price-step-interval" type="number" min="1" max="1440" step="1" wire:model="priceRows.0.step_interval_minutes" required class="ui-input">
                                    @error('priceRows.0.step_interval_minutes') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <label for="price-min-duration" class="ui-label">Durasi minimum ({{ $pricingUnit === 'hourly' ? 'jam' : 'hari' }})</label>
                                <input id="price-min-duration" type="number" min="1" max="65535" step="1" wire:model="priceRows.0.min_duration_units" required class="ui-input">
                                @error('priceRows.0.min_duration_units') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="price-max-duration" class="ui-label">Durasi maksimum ({{ $pricingUnit === 'hourly' ? 'jam' : 'hari' }})</label>
                                <input id="price-max-duration" type="number" min="1" max="65535" step="1" wire:model="priceRows.0.max_duration_units" required class="ui-input">
                                @error('priceRows.0.max_duration_units') <p role="alert" class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            @error('priceRows') <p role="alert" class="ui-error sm:col-span-2">{{ $message }}</p> @enderror
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
                    <div><p class="text-sm font-bold">Foto, harga, dan jadwal</p><p class="mt-1 text-xs leading-5 text-[var(--muted)]">Foto, harga, jadwal, dan blokir dikelola pada bagian formulir terkait; promosi tersedia melalui daftar venue.</p></div>
                </div>
            </div>
        </aside>
    </div>
</div>