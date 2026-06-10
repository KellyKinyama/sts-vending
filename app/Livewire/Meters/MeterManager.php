<?php

namespace App\Livewire\Meters;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\Tariff;
use App\Models\VendingKey;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MeterManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sg')]
    public string $supplyGroupFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $pan = '';
    public string $iin = '';
    public string $iain = '';
    public ?string $manufacturer_code = null;
    public ?string $decoder_serial_number = null;
    public ?int $supply_group_id = null;
    public ?int $vending_key_id = null;
    public ?int $tariff_id = null;
    public ?int $customer_id = null;
    public ?string $location = null;
    public ?string $installed_at = null;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'pan'                    => ['required', 'string', 'size:18', 'regex:/^\d{18}$/', Rule::unique('meters', 'pan')->ignore($this->editingId)],
            'iin'                    => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'iain'                   => ['required', 'string', 'regex:/^\d{11,13}$/'],
            'manufacturer_code'      => ['nullable', 'string', 'max:4'],
            'decoder_serial_number'  => ['nullable', 'string', 'max:8'],
            'supply_group_id'        => ['required', 'integer', 'exists:supply_groups,id'],
            'vending_key_id'         => ['nullable', 'integer', 'exists:vending_keys,id'],
            'tariff_id'              => ['nullable', 'integer', 'exists:tariffs,id'],
            'customer_id'            => ['nullable', 'integer', 'exists:customers,id'],
            'location'               => ['nullable', 'string', 'max:200'],
            'installed_at'           => ['nullable', 'date'],
            'is_active'              => ['boolean'],
        ];
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'supplyGroupFilter'], true)) {
            $this->resetPage();
        }
    }

    public function newMeter(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $m = Meter::findOrFail($id);
        $this->editingId             = $m->id;
        $this->pan                   = $m->pan;
        $this->iin                   = $m->iin;
        $this->iain                  = $m->iain;
        $this->manufacturer_code     = $m->manufacturer_code;
        $this->decoder_serial_number = $m->decoder_serial_number;
        $this->supply_group_id       = $m->supply_group_id;
        $this->vending_key_id        = $m->vending_key_id;
        $this->tariff_id             = $m->tariff_id;
        $this->customer_id           = $m->customer_id;
        $this->location              = $m->location;
        $this->installed_at          = optional($m->installed_at)->format('Y-m-d');
        $this->is_active             = (bool) $m->is_active;
        $this->showForm              = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        if ($this->editingId) {
            Meter::findOrFail($this->editingId)->update($data);
            session()->flash('sts.flash', 'Meter updated.');
        } else {
            Meter::create($data);
            session()->flash('sts.flash', 'Meter created.');
        }
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Meter::findOrFail($id)->delete();
        session()->flash('sts.flash', 'Meter deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'pan', 'iin', 'iain', 'manufacturer_code', 'decoder_serial_number',
            'supply_group_id', 'vending_key_id', 'tariff_id', 'customer_id',
            'location', 'installed_at',
        ]);
        $this->is_active = true;
        $this->resetErrorBag();
    }

    #[Computed]
    public function supplyGroups()
    {
        return SupplyGroup::query()->orderBy('code')->get(['id', 'code', 'name']);
    }

    #[Computed]
    public function vendingKeys()
    {
        return VendingKey::query()->orderBy('name')->get(['id', 'name', 'algorithm']);
    }

    #[Computed]
    public function tariffs()
    {
        return Tariff::query()->orderBy('name')->get(['id', 'name', 'currency', 'rate_per_kwh']);
    }

    #[Computed]
    public function customers()
    {
        return Customer::query()->orderBy('account_number')->get(['id', 'account_number', 'name']);
    }

    public function render()
    {
        $meters = Meter::query()
            ->with(['supplyGroup:id,code,name', 'vendingKey:id,name', 'tariff:id,name', 'customer:id,account_number,name'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('pan', 'like', "%{$this->search}%")
                  ->orWhere('iin', 'like', "%{$this->search}%")
                  ->orWhere('iain', 'like', "%{$this->search}%")
                  ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->when($this->supplyGroupFilter, fn ($q) => $q->where('supply_group_id', $this->supplyGroupFilter))
            ->orderBy('pan')
            ->paginate(15);

        return view('livewire.meters.meter-manager', ['meters' => $meters]);
    }
}
