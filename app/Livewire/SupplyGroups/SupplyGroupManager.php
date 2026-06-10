<?php

namespace App\Livewire\SupplyGroups;

use App\Models\SupplyGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SupplyGroupManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public ?string $utility = null;
    public ?string $region = null;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'size:6', 'regex:/^\d{6}$/', Rule::unique('supply_groups', 'code')->ignore($this->editingId)],
            'name'      => ['required', 'string', 'max:120'],
            'utility'   => ['nullable', 'string', 'max:120'],
            'region'    => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ];
    }

    public function updating($name): void
    {
        if ($name === 'search') {
            $this->resetPage();
        }
    }

    public function newSupplyGroup(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $sg = SupplyGroup::findOrFail($id);
        $this->editingId = $sg->id;
        $this->code      = $sg->code;
        $this->name      = $sg->name;
        $this->utility   = $sg->utility;
        $this->region    = $sg->region;
        $this->is_active = (bool) $sg->is_active;
        $this->showForm  = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            SupplyGroup::findOrFail($this->editingId)->update($data);
            session()->flash('sts.flash', 'Supply group updated.');
        } else {
            SupplyGroup::create($data);
            session()->flash('sts.flash', 'Supply group created.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        SupplyGroup::findOrFail($id)->delete();
        session()->flash('sts.flash', 'Supply group deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'utility', 'region']);
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $supplyGroups = SupplyGroup::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%")
                  ->orWhere('utility', 'like', "%{$this->search}%")
                  ->orWhere('region', 'like', "%{$this->search}%");
            }))
            ->orderBy('code')
            ->paginate(15);

        return view('livewire.supply-groups.supply-group-manager', [
            'supplyGroups' => $supplyGroups,
        ]);
    }
}
