<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DuplicateCheckInException extends HttpException
{
    public function __construct(
        string $message,
        public readonly ?string $checkedInAt = null,
        public readonly ?int $checkedInBy = null,
        public readonly ?string $staffName = null,
    ) {
        parent::__construct(422, $message);
    }

    /**
     * Render the exception into an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
            'data' => [
                'checked_in_at' => $this->checkedInAt,
                'checked_in_by' => $this->checkedInBy,
                'staff_name' => $this->staffName,
            ],
        ], 422);
    }
}
