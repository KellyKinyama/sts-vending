<?php

namespace App\Livewire\Tariffs;

use App\Models\SupplyGroup;
use App\Models\Tariff;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TariffManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'sg')]
    public string $supplyGroupFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public ?int $supply_group_id = null;
    public string $name = '';
    public ?string $code = null;
    public ?float $rate_per_kwh = null;
    public string $currency = 'ZMW';
    public ?int $max_power_limit_w = null;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'supply_group_id'   => ['required', 'integer', 'exists:supply_groups,id'],
            'name'              => ['required', 'string', 'max:120'],
            'code'              => ['nullable', 'string', 'max:50'],
            'rate_per_kwh'      => ['required', 'numeric', 'min:0'],
            'currency'          => ['required', 'string', 'size:3'],
            'max_power_limit_w' => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['boolean'],
        ];
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'supplyGroupFilter'], true)) {
            $this->resetPage();
        }
    }

    public function newTariff(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = Tariff::findOrFail($id);
        $this->editingId         = $t->id;
        $this->supply_group_id   = $t->supply_group_id;
        $this->name              = $t->name;
        $this->code              = $t->code;
        $this->rate_per_kwh      = (float) $t->rate_per_kwh;
        $this->currency          = $t->currency;
        $this->max_power_limit_w = $t->max_power_limit_w;
        $this->is_active         = (bool) $t->is_active;
        $this->showForm          = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        if ($this->editingId) {
            Tariff::findOrFail($this->editingId)->update($data);
            session()->flash('sts.flash', 'Tariff updated.');
        } else {
            Tariff::create($data);
            session()->flash('sts.flash', 'Tariff created.');
        }
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Tariff::findOrFail($id)->delete();
        session()->flash('sts.flash', 'Tariff deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'supply_group_id', 'name', 'code', 'rate_per_kwh', 'max_power_limit_w']);
        $this->currency  = 'ZMW';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    #[Computed]
    public function supplyGroups()
    {
        return SupplyGroup::query()->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function render()
    {
        $tariffs = Tariff::query()
            ->with('supplyGroup:id,code,name')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%");
            }))
            ->when($this->supplyGroupFilter, fn ($q) => $q->where('supply_group_id', $this->supplyGroupFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.tariffs.tariff-manager', ['tariffs' => $tariffs]);
    }
}
