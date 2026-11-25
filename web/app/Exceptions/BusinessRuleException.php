<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violación de una regla de negocio (p. ej. saltar un estado o cobrar una mesa sin entregar).
 * Se responde como 422 con un mensaje claro en español.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'business_rule')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->reason], 422);
    }
}
