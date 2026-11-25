@php
    use App\Enums\OrderStatus;
    use App\Support\LocalTime;
    use App\Support\Money;
    $fmtMinutes = fn (?int $m) => $m === null ? '—' : ($m < 60 ? "$m min" : intdiv($m, 60).' h '.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT).' min');
@endphp

<div>
    <x-slot:nav><x-admin-nav /></x-slot:nav>

    <a href="{{ route('admin.orders.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 font-semibold text-st-preparacion hover:underline">
        ← Volver a órdenes
    </a>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold">Orden #{{ $order->id }}</h1>
        <x-status-badge :status="$order->status" class="text-sm" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <dl class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-pizarra/60">Mesa</dt><dd class="text-lg font-bold">{{ $order->diningTable->number }}</dd></div>
                <div><dt class="text-pizarra/60">Mesero</dt><dd class="font-semibold">{{ $order->waiter->name }}</dd></div>
                <div><dt class="text-pizarra/60">Enviada</dt><dd class="font-semibold">{{ LocalTime::format($order->created_at) }}</dd></div>
                <div><dt class="text-pizarra/60">Tiempo total</dt><dd class="font-semibold" data-testid="total-time">{{ $fmtMinutes($totalMinutes) }}</dd></div>
            </dl>

            @if ($order->status === OrderStatus::Cancelada)
                <p class="mb-4 rounded-xl bg-st-cancelada/15 px-4 py-3"><span class="font-bold">Motivo de cancelación:</span> {{ $order->cancel_reason ?? '—' }}</p>
            @endif

            <table class="w-full text-left text-sm">
                <thead class="border-b border-pizarra/10 text-xs uppercase text-pizarra/60">
                    <tr>
                        <th class="py-2">Platillo</th>
                        <th class="py-2 text-center">Cant.</th>
                        <th class="py-2 text-right">P. unitario</th>
                        <th class="py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-pizarra/5">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-2">
                                <p class="font-semibold">{{ $item->product_name }} <span class="text-xs font-normal text-pizarra/50">({{ $item->station->label() }})</span></p>
                                @if (! empty($item->modifiers))
                                    <p class="text-xs text-st-preparacion">{{ collect($item->modifiers)->map(fn ($m) => $m['name'].($m['price_delta_cents'] ? ' +'.Money::format($m['price_delta_cents']) : ''))->join(' · ') }}</p>
                                @endif
                                @if ($item->note)
                                    <p class="mt-1 inline-block rounded bg-acento/25 px-2 text-xs font-semibold">Nota: {{ $item->note }}</p>
                                @endif
                            </td>
                            <td class="py-2 text-center">{{ $item->quantity }}</td>
                            <td class="py-2 text-right">{{ Money::format($item->unit_price_cents) }}</td>
                            <td class="py-2 text-right font-semibold">{{ Money::format($item->subtotal_cents) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-pizarra/20">
                        <td colspan="3" class="py-3 text-right font-bold">Total</td>
                        <td class="py-3 text-right text-lg font-extrabold">{{ Money::format($order->total_cents) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <div class="space-y-4">
            <section class="card p-5">
                <h2 class="mb-3 font-bold">Historial de estados</h2>
                <ol class="relative ml-2 border-l-2 border-pizarra/15">
                    @foreach ($steps as $step)
                        <li class="mb-4 ml-4">
                            <span class="absolute -left-[7px] mt-1.5 size-3 rounded-full bg-pizarra"></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$step['entry']->to_status" />
                                <span class="text-sm font-semibold">{{ LocalTime::format($step['entry']->created_at, 'd/m H:i:s') }}</span>
                            </div>
                            <p class="text-xs text-pizarra/60">
                                {{ $step['entry']->user->name ?? 'Sistema' }}
                                @if ($step['minutes'] !== null) · duró {{ $fmtMinutes($step['minutes']) }} @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($order->payments->isNotEmpty())
                <section class="card p-5">
                    <h2 class="mb-3 font-bold">Pagos de la mesa</h2>
                    <ul class="space-y-2 text-sm">
                        @foreach ($order->payments as $payment)
                            <li class="flex justify-between gap-2">
                                <span>{{ $payment->method->label() }} · {{ LocalTime::format($payment->created_at, 'H:i') }} · {{ $payment->user->name }}</span>
                                <span class="font-semibold">{{ Money::format($payment->amount_cents) }}@if ($payment->tip_cents > 0) <span class="text-xs text-pizarra/60">+{{ Money::format($payment->tip_cents) }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($order->status === OrderStatus::Pendiente)
                <section class="card p-5">
                    <h2 class="mb-2 font-bold">Cancelar orden</h2>
                    <form wire:submit="cancel" class="space-y-2">
                        <label for="reason" class="label">Motivo</label>
                        <input id="reason" type="text" wire:model="reason" class="input" maxlength="255" placeholder="Ej. el cliente se retiró">
                        @error('reason') <p class="text-sm font-semibold text-salida">{{ $message }}</p> @enderror
                        @if ($error) <p class="text-sm font-semibold text-salida">{{ $error }}</p> @endif
                        <button type="submit" class="btn-danger w-full" wire:confirm="¿Seguro que quieres cancelar la orden #{{ $order->id }}?">Cancelar orden</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</div>
