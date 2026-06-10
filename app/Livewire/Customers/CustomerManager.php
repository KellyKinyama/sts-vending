<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CustomerManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $account_number = '';
    public string $name = '';
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $address = null;
    public ?string $city = null;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'account_number' => ['required', 'string', 'max:60', Rule::unique('customers', 'account_number')->ignore($this->editingId)],
            'name'           => ['required', 'string', 'max:120'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:120'],
            'address'        => ['nullable', 'string', 'max:200'],
            'city'           => ['nullable', 'string', 'max:80'],
            'is_active'      => ['boolean'],
        ];
    }

    public function updating($name): void
    {
        if ($name === 'search') {
            $this->resetPage();
        }
    }

    public function newCustomer(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $c = Customer::findOrFail($id);
        $this->editingId      = $c->id;
        $this->account_number = $c->account_number;
        $this->name           = $c->name;
        $this->phone          = $c->phone;
        $this->email          = $c->email;
        $this->address        = $c->address;
        $this->city           = $c->city;
        $this->is_active      = (bool) $c->is_active;
        $this->showForm       = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        if ($this->editingId) {
            Customer::findOrFail($this->editingId)->update($data);
            session()->flash('sts.flash', 'Customer updated.');
        } else {
            Customer::create($data);
            session()->flash('sts.flash', 'Customer created.');
        }
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Customer::findOrFail($id)->delete();
        session()->flash('sts.flash', 'Customer deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'account_number', 'name', 'phone', 'email', 'address', 'city']);
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('account_number', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('account_number')
            ->paginate(15);

        return view('livewire.customers.customer-manager', ['customers' => $customers]);
    }
}
