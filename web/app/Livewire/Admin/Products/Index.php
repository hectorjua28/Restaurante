<?php

namespace App\Livewire\Admin\Products;

use App\Actions\Admin\DeleteProduct;
use App\Actions\Products\SetProductAvailability;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Platillos')]
class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    #[Url(as: 'categoria', except: '')]
    public string $category = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->flash = (string) session('flash', '');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id, SetProductAvailability $action): void
    {
        $product = Product::findOrFail($id);
        $this->authorize('toggleAvailability', $product);
        $action->handle($product, ! $product->available, auth()->user());
    }

    public function delete(int $id, DeleteProduct $action): void
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $action->handle($product, auth()->user());
        $this->flash = "«{$product->name}» se eliminó del menú.";
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('livewire.admin.products.index', [
            'products' => Product::query()
                ->with(['category', 'modifiers'])
                ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->when(ctype_digit($this->category), fn ($q) => $q->where('category_id', (int) $this->category))
                ->orderBy('name')
                ->paginate(20),
            'categories' => Category::orderBy('position')->get(['id', 'name']),
        ]);
    }
}
