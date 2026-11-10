<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotUnavailableException extends Exception
{
    public function __construct(string $message = 'One or more selected slots are currently held or no longer available.')
    {
        parent::__construct($message, 409);
    }

    /**
     * Render the exception into an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
        ], 409);
    }
}
