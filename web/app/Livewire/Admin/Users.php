<?php

namespace App\Livewire\Admin;

use App\Actions\Admin\SaveUser;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Usuarios del sistema. No se borran (dejan historial en órdenes y pagos): se desactivan.
 */
#[Layout('components.layouts.app')]
#[Title('Usuarios')]
class Users extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'mesero';

    public bool $active = true;

    public string $password = '';

    public string $flash = '';

    public string $flashType = 'success';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(Role::class)],
            'active' => ['boolean'],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo electrónico', 'role' => 'rol', 'password' => 'contraseña'];
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->active = $user->active;
        $this->password = '';
        $this->resetValidation();
    }

    public function save(SaveUser $action): void
    {
        $this->validate();

        try {
            $user = $action->handle(
                $this->editingId ? User::findOrFail($this->editingId) : null,
                [
                    'name' => trim($this->name),
                    'email' => $this->email,
                    'role' => $this->role,
                    'active' => $this->active,
                    'password' => $this->password !== '' ? $this->password : null,
                ],
                auth()->user(),
            );
            $this->notify("Usuario «{$user->name}» guardado.");
            $this->resetForm();
        } catch (BusinessRuleException $e) {
            $this->notify($e->getMessage(), 'error');
        }
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'role', 'active', 'password');
        $this->resetValidation();
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->flash = $message;
        $this->flashType = $type;
    }

    public function render(): View
    {
        return view('livewire.admin.users', [
            'users' => User::orderByDesc('active')->orderBy('role')->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
