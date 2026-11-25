<?php

namespace App\Livewire\Admin\Products;

use App\Actions\Admin\SaveProduct;
use App\Enums\Station;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Alta y edición de un platillo con sus modificadores. Los precios se capturan en pesos
 * y se guardan en centavos enteros.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Product $product = null;

    public string $name = '';

    public string $description = '';

    public string $categoryId = '';

    public string $price = '';

    public string $station = 'cocina';

    public bool $available = true;

    /** @var list<array{id: int|null, name: string, price: string}> */
    public array $modifiers = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->authorize('update', $product);
            $this->product = $product->load('modifiers');
            $this->name = $product->name;
            $this->description = $product->description ?? '';
            $this->categoryId = (string) $product->category_id;
            $this->price = Money::toPesosString($product->price_cents);
            $this->station = $product->station->value;
            $this->available = $product->available;
            $this->modifiers = $product->modifiers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price' => Money::toPesosString($m->price_delta_cents),
            ])->values()->all();
        } else {
            $this->authorize('create', Product::class);
            $this->categoryId = (string) (Category::orderBy('position')->value('id') ?? '');
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $money = ['required', 'regex:/^\d{1,6}(\.\d{1,2})?$/'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'categoryId' => ['required', Rule::exists('categories', 'id')],
            'price' => $money,
            'station' => ['required', Rule::enum(Station::class)],
            'available' => ['boolean'],
            'modifiers' => ['array', 'max:15'],
            'modifiers.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'modifiers.*.price' => $money,
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'categoryId' => 'categoría',
            'price' => 'precio',
            'station' => 'estación',
            'modifiers.*.name' => 'nombre del modificador',
            'modifiers.*.price' => 'precio extra',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'price.regex' => 'El precio debe ser una cantidad en pesos, p. ej. 45 o 45.50.',
            'modifiers.*.price.regex' => 'El precio extra debe ser una cantidad en pesos, p. ej. 0 o 10.50.',
            'modifiers.*.name.distinct' => 'Hay modificadores repetidos.',
        ];
    }

    public function addModifier(): void
    {
        $this->modifiers[] = ['id' => null, 'name' => '', 'price' => '0'];
    }

    public function removeModifier(int $index): void
    {
        unset($this->modifiers[$index]);
        $this->modifiers = array_values($this->modifiers);
    }

    public function save(SaveProduct $action): void
    {
        $this->validate();

        $product = $action->handle(
            $this->product,
            [
                'category_id' => (int) $this->categoryId,
                'name' => trim($this->name),
                'description' => trim($this->description) ?: null,
                'price_cents' => Money::toCents($this->price),
                'station' => $this->station,
                'available' => $this->available,
            ],
            array_map(fn (array $m) => [
                'id' => $m['id'] ?? null,
                'name' => trim($m['name']),
                'price_delta_cents' => Money::toCents($m['price']),
            ], $this->modifiers),
            auth()->user(),
        );

        session()->flash('flash', "«{$product->name}» se guardó correctamente.");
        $this->redirectRoute('admin.products.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::orderBy('position')->get(['id', 'name']),
            'stations' => Station::cases(),
        ])->title($this->product ? "Editar {$this->product->name}" : 'Nuevo platillo');
    }
}
