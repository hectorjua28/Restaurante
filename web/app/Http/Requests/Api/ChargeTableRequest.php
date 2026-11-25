<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargeTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('charge', $this->route('table')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $money = ['regex:/^\d{1,7}(\.\d{1,2})?$/'];

        return [
            /** Uno o varios pagos (cuenta dividida). La suma de `amount` debe ser igual al total. */
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            /** Monto en pesos como texto, p. ej. "250.50". */
            'payments.*.amount' => ['required', 'string', ...$money],
            /** Propina en pesos como texto (opcional). */
            'payments.*.tip' => ['nullable', 'string', ...$money],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.*.amount.regex' => 'El monto debe ser una cantidad en pesos con hasta dos decimales.',
            'payments.*.tip.regex' => 'La propina debe ser una cantidad en pesos con hasta dos decimales.',
        ];
    }

    /** @return list<array{method: PaymentMethod, amount_cents: int, tip_cents: int}> */
    public function payments(): array
    {
        /** @var list<array{method:string, amount:string, tip?:string|null}> $rows */
        $rows = $this->validated('payments');

        return array_map(fn (array $p) => [
            'method' => PaymentMethod::from($p['method']),
            'amount_cents' => Money::toCents($p['amount']),
            'tip_cents' => Money::toCents($p['tip'] ?? '0'),
        ], $rows);
    }
}
