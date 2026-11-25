<?php

namespace App\Livewire\Admin;

use App\Actions\Admin\SaveRecord;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DiningTable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mesas')]
class Tables extends Component
{
    public ?int $editingId = null;

    public string $number = '';

    public string $capacity = '4';

    public string $flash = '';

    public string $flashType = 'success';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('tables', 'number')->ignore($this->editingId)],
            'capacity' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['number' => 'número de mesa', 'capacity' => 'capacidad'];
    }

    public function edit(int $id): void
    {
        $table = DiningTable::findOrFail($id);
        $this->editingId = $table->id;
        $this->number = (string) $table->number;
        $this->capacity = (string) $table->capacity;
        $this->resetValidation();
    }

    public function save(SaveRecord $action): void
    {
        $this->validate();
        $table = $this->editingId ? DiningTable::findOrFail($this->editingId) : new DiningTable;
        $action->save($table, ['number' => (int) $this->number, 'capacity' => (int) $this->capacity], 'table', auth()->user());

        $this->notify("Mesa {$table->number} guardada.");
        $this->resetForm();
    }

    public function delete(int $id, SaveRecord $action): void
    {
        $table = DiningTable::findOrFail($id);

        try {
            if ($table->orders()->whereIn('status', OrderStatus::active())->exists()) {
                throw new BusinessRuleException("La mesa {$table->number} tiene órdenes activas.");
            }
            $action->delete($table, 'table', auth()->user(),
                "No se puede eliminar la mesa {$table->number} porque tiene órdenes o pagos en el historial.");
            $this->notify("Mesa {$table->number} eliminada.");
        } catch (BusinessRuleException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'number', 'capacity');
        $this->resetValidation();
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }

    public function render(): View
    {
        return view('livewire.admin.tables', [
            'tables' => DiningTable::withCount('activeOrders')->orderBy('number')->get(),
        ]);
    }
}
