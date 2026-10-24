<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'type' => 'terms_and_conditions',
                'version' => 'v1.0',
                'title' => 'Syarat dan Ketentuan Penggunaan Platform Adatempat',
                'content' => $this->termsAndConditions(),
                'status' => 'draft',
                'published_at' => null,
            ],
            [
                'type' => 'privacy_policy',
                'version' => 'v1.0',
                'title' => 'Kebijakan Privasi Platform Adatempat',
                'content' => $this->privacyPolicy(),
                'status' => 'draft',
                'published_at' => null,
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

    private function termsAndConditions(): string
    {
        return <<<'TEXT'
TEMPELKAN TEKS LENGKAP BAGIAN 18 PRD v1.5 DI SINI
TEXT;
    }

    private function privacyPolicy(): string
    {
        return <<<'TEXT'
TEMPELKAN TEKS LENGKAP BAGIAN 19 PRD v1.5 DI SINI
TEXT;
    }
}
