<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessController extends Controller
{
    public function show(Business $business): JsonResponse
    {
        Gate::authorize('view', $business);

        return response()->json([
            'data' => $business->only(['id', 'name', 'legal_name', 'status']),
        ]);
    }
}
