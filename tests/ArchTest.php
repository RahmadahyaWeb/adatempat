<?php

declare(strict_types=1);

arch('application files use strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('domain models extend eloquent model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('status enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('controllers delegate business logic and do not execute direct transaction logic', function () {
    $controllerPath = __DIR__.'/../app/Http/Controllers';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerPath));

    $disallowedPatterns = [
        '/\bDB::transaction\s*\(/',
        '/\bDB::beginTransaction\s*\(/',
        '/\bDB::commit\s*\(/',
        '/\bDB::rollBack\s*\(/',
        '/\bbeginTransaction\s*\(/',
        '/\brollBack\s*\(/',
    ];

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());
            foreach ($disallowedPatterns as $pattern) {
                expect(preg_match($pattern, $contents))->toBe(
                    0,
                    "Controller {$file->getFilename()} must not execute direct transaction logic; delegate to dedicated service layer."
                );
            }
        }
    }
});

arch('controllers do not mutate model status directly', function () {
    $controllerPath = __DIR__.'/../app/Http/Controllers';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerPath));

    $disallowedPatterns = [
        '/->status\s*=\s*/',
        '/update\s*\(\s*\[[^\]]*status/s',
    ];

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());
            foreach ($disallowedPatterns as $pattern) {
                expect(preg_match($pattern, $contents))->toBe(
                    0,
                    "Controller {$file->getFilename()} must not directly mutate model status; delegate to dedicated service layer."
                );
            }
        }
    }
});

arch('debugging statements are not used')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();
