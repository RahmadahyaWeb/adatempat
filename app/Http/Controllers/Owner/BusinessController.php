<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businesses = $request->user()
            ->businesses()
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'status']);

        return response()->json(['data' => $businesses]);
    }

    public function show(Business $business): JsonResponse
    {
        Gate::authorize('view', $business);

        return response()->json([
            'data' => $business->only(['id', 'name', 'legal_name', 'status']),
        ]);
    }
}
