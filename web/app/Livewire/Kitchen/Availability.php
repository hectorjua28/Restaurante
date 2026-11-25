<?php

namespace App\Livewire\Kitchen;

use App\Actions\Products\SetProductAvailability;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Marcar platillos como agotados o disponibles (solo afecta comandas nuevas).
 */
#[Layout('components.layouts.app')]
#[Title('Platillos agotados')]
class Availability extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public bool $onlySoldOut = false;

    public string $flash = '';

    public function toggle(int $productId, SetProductAvailability $action): void
    {
        $product = Product::findOrFail($productId);
        $this->authorize('toggleAvailability', $product);

        $product = $action->handle($product, ! $product->available, auth()->user());
        $this->flash = $product->available
            ? "«{$product->name}» está disponible de nuevo."
            : "«{$product->name}» marcado como agotado.";
    }

    public function render(): View
    {
        $term = trim($this->search);

        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->with(['products' => fn ($q) => $q
                ->when($term !== '', fn ($p) => $p->where('name', 'like', "%{$term}%"))
                ->when($this->onlySoldOut, fn ($p) => $p->where('available', false))
                ->orderBy('name')])
            ->get()
            ->filter(fn (Category $c) => $c->products->isNotEmpty());

        return view('livewire.kitchen.availability', [
            'categories' => $categories,
            'soldOutCount' => Product::where('available', false)->count(),
        ]);
    }
}
