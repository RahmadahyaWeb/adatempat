<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReschedulePolicyException extends HttpException
{
    public function __construct(string $message, int $statusCode = 422)
    {
        parent::__construct($statusCode, $message);
    }

    /**
     * Render the exception into an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
        ], $this->getStatusCode());
    }
}
