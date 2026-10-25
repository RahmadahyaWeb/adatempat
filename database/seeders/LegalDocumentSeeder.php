<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;
use RuntimeException;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $isLocalPreview = app()->environment('local');

        $documents = [
            [
                'type' => 'terms_and_conditions',
                'version' => 'v1.0',
                'title' => 'Syarat dan Ketentuan Penggunaan Platform Adatempat',
                'content' => $this->readDraft('docs/legal/terms-and-conditions-v1.0.md'),
                'status' => $isLocalPreview ? 'published' : 'draft',
                'published_at' => $isLocalPreview ? now() : null,
            ],
            [
                'type' => 'privacy_policy',
                'version' => 'v1.0',
                'title' => 'Kebijakan Privasi Platform Adatempat',
                'content' => $this->readDraft('docs/legal/privacy-policy-v1.0.md'),
                'status' => $isLocalPreview ? 'published' : 'draft',
                'published_at' => $isLocalPreview ? now() : null,
            ],
        ];

        foreach ($documents as $document) {
            LegalDocument::updateOrCreate(
                [
                    'type' => $document['type'],
                    'version' => $document['version'],
                ],
                $document,
            );
        }
    }

    private function readDraft(string $relativePath): string
    {
        $path = base_path($relativePath);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Unable to read legal document draft: {$relativePath}");
        }

        return $content;
    }
}
