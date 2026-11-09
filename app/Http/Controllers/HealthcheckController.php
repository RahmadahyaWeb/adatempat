<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthcheckController extends Controller
{
    /**
     * Handle the incoming healthcheck request.
     */
    public function __invoke(): JsonResponse
    {
        $databaseOk = false;
        $cacheOk = false;

        try {
            DB::connection()->getPdo();
            $databaseOk = true;
        } catch (Throwable) {
            $databaseOk = false;
        }

        try {
            Cache::put('healthcheck_ping', 'pong', 5);
            $cacheOk = Cache::get('healthcheck_ping') === 'pong';
        } catch (Throwable) {
            $cacheOk = false;
        }

        $allHealthy = $databaseOk && $cacheOk;

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'error',
            'services' => [
                'database' => $databaseOk ? 'ok' : 'error',
                'cache' => $cacheOk ? 'ok' : 'error',
            ],
        ], $allHealthy ? 200 : 503);
    }
}
