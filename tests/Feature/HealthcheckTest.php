<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('healthcheck route returns 200 and confirms database and cache connectivity', function () {
    $response = $this->getJson('/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'services' => [
                'database' => 'ok',
                'cache' => 'ok',
            ],
        ]);
});

test('healthcheck verifies cache operations and redis atomic lock functionality', function () {
    $response = $this->getJson('/health');

    $response->assertStatus(200);

    // Verify cache store can set and retrieve values
    Cache::put('healthcheck_test_key', 'active', 10);
    expect(Cache::get('healthcheck_test_key'))->toBe('active');

    // Verify Redis lock can be acquired and released
    $lock = Cache::lock('healthcheck_lock_test', 10);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('healthcheck returns 503 if database connection fails', function () {
    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new RuntimeException('Database unreachable'));

    $response = $this->getJson('/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'error',
            'services' => [
                'database' => 'error',
                'cache' => 'ok',
            ],
        ]);
});

test('healthcheck returns 503 if cache store fails', function () {
    Cache::shouldReceive('put')
        ->once()
        ->andThrow(new RuntimeException('Redis connection refused'));

    $response = $this->getJson('/health');

    $response->assertStatus(503)
        ->assertJson([
            'status' => 'error',
            'services' => [
                'database' => 'ok',
                'cache' => 'error',
            ],
        ]);
});
