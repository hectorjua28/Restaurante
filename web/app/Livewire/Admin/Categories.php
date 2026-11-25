<?php

namespace App\Livewire\Admin;

use App\Actions\Admin\SaveRecord;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Categorías')]
class Categories extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $position = '';

    public bool $active = true;

    public string $flash = '';

    public string $flashType = 'success';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($this->editingId)],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['name' => 'nombre', 'position' => 'orden'];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->position = (string) ((int) Category::max('position') + 1);
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->position = (string) $category->position;
        $this->active = $category->active;
        $this->resetValidation();
    }

    public function save(SaveRecord $action): void
    {
        $this->validate();
        $category = $this->editingId ? Category::findOrFail($this->editingId) : new Category;
        $action->save($category, [
            'name' => trim($this->name),
            'position' => (int) $this->position,
            'active' => $this->active,
        ], 'category', auth()->user());

        $this->notify("Categoría «{$category->name}» guardada.");
        $this->resetForm();
    }

    public function delete(int $id, SaveRecord $action): void
    {
        $category = Category::findOrFail($id);
        try {
            $action->delete($category, 'category', auth()->user(),
                "No se puede eliminar «{$category->name}» porque tiene platillos (también los eliminados). Desactívala en su lugar.");
            $this->notify("Categoría «{$category->name}» eliminada.");
        } catch (BusinessRuleException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'position', 'active');
        $this->resetValidation();
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }

    public function render(): View
    {
        return view('livewire.admin.categories', [
            'categories' => Category::withCount(['products' => fn ($q) => $q->withTrashed()])->orderBy('position')->orderBy('name')->get(),
        ]);
    }
}
