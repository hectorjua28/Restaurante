@props(['message' => '', 'type' => 'success'])

@if ($message !== '' && $message !== null)
    <div wire:key="flash-{{ md5($message.$type) }}" role="status"
        x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
        @class([
            'mb-4 rounded-xl px-4 py-3 font-semibold',
            'bg-st-lista/15 text-green-800' => $type === 'success',
            'bg-salida/10 text-salida' => $type === 'error',
        ])>
        {{ $message }}
    </div>
@endif
