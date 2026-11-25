<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('order')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /** Motivo de la cancelación (obligatorio). */
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
