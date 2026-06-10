<?php

namespace App\Livewire\VendingKeys;

use App\Models\SupplyGroup;
use App\Models\VendingKey;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class VendingKeyManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sg')]
    public string $supplyGroupFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public ?int $supply_group_id = null;
    public int $key_type = 2;
    public string $tariff_index = '01';
    public int $key_revision_number = 1;
    public int $key_expiry_number = 255;
    public string $algorithm = 'DKGA02';
    public string $encryption_algorithm = 'EA07';
    public int $base_date = 1993;
    public string $vudk_blob = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:120'],
            'supply_group_id'      => ['required', 'integer', 'exists:supply_groups,id'],
            'key_type'             => ['required', 'integer', 'between:1,7'],
            'tariff_index'         => ['required', 'string', 'size:2'],
            'key_revision_number'  => ['required', 'integer', 'between:0,15'],
            'key_expiry_number'    => ['required', 'integer', 'between:0,255'],
            'algorithm'            => ['required', 'in:DKGA02,DKGA04'],
            'encryption_algorithm' => ['required', 'in:EA07,EA11'],
            'base_date'            => ['required', 'integer', 'between:1980,2100'],
            'vudk_blob'            => ['required', 'string', 'regex:/^[0-9a-fA-F]+$/', 'min:16'],
            'is_active'            => ['boolean'],
        ];
    }

    public function updated($name): void
    {
        if ($name === 'algorithm') {
            // DKGA02 = 8 bytes = 16 hex; DKGA04 = 20 bytes = 40 hex
            $this->vudk_blob = '';
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'supplyGroupFilter'], true)) {
            $this->resetPage();
        }
    }

    public function newVendingKey(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $k = VendingKey::findOrFail($id);
        $this->editingId            = $k->id;
        $this->name                 = $k->name;
        $this->supply_group_id      = $k->supply_group_id;
        $this->key_type             = $k->key_type;
        $this->tariff_index         = $k->tariff_index;
        $this->key_revision_number  = $k->key_revision_number;
        $this->key_expiry_number    = $k->key_expiry_number;
        $this->algorithm            = $k->algorithm;
        $this->encryption_algorithm = $k->encryption_algorithm;
        $this->base_date            = $k->base_date;
        // Show current vudk so editor can rotate/replace; sensitive — accessor returns hex.
        $this->vudk_blob            = $k->vudk_blob ?? '';
        $this->is_active            = (bool) $k->is_active;
        $this->showForm             = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $expected = $data['algorithm'] === 'DKGA02' ? 16 : 40;
        if (strlen($data['vudk_blob']) !== $expected) {
            $this->addError('vudk_blob', "VUDK for {$data['algorithm']} must be exactly {$expected} hex chars.");
            return;
        }

        if ($this->editingId) {
            VendingKey::findOrFail($this->editingId)->update($data);
            session()->flash('sts.flash', 'Vending key updated.');
        } else {
            VendingKey::create($data);
            session()->flash('sts.flash', 'Vending key created.');
        }
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        VendingKey::findOrFail($id)->delete();
        session()->flash('sts.flash', 'Vending key deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'supply_group_id', 'tariff_index', 'vudk_blob',
        ]);
        $this->key_type             = 2;
        $this->tariff_index         = '01';
        $this->key_revision_number  = 1;
        $this->key_expiry_number    = 255;
        $this->algorithm            = 'DKGA02';
        $this->encryption_algorithm = 'EA07';
        $this->base_date            = 1993;
        $this->is_active            = true;
        $this->resetErrorBag();
    }

    #[Computed]
    public function supplyGroups()
    {
        return SupplyGroup::query()->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function render()
    {
        $vendingKeys = VendingKey::query()
            ->with('supplyGroup:id,code,name')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('algorithm', 'like', "%{$this->search}%")
                  ->orWhere('encryption_algorithm', 'like', "%{$this->search}%");
            }))
            ->when($this->supplyGroupFilter, fn ($q) => $q->where('supply_group_id', $this->supplyGroupFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.vending-keys.vending-key-manager', ['vendingKeys' => $vendingKeys]);
    }
}
