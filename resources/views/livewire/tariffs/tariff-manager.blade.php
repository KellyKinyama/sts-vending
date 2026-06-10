<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">{{ session('sts.flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Tariffs</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, code..." class="border rounded px-2 py-1 text-sm w-72">
            <select wire:model.live="supplyGroupFilter" class="border rounded px-2 py-1 text-sm">
                <option value="">All supply groups</option>
                @foreach ($this->supplyGroups as $sg)
                    <option value="{{ $sg->id }}">{{ $sg->code }} — {{ $sg->name }}</option>
                @endforeach
            </select>
            <button wire:click="newTariff" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ New tariff</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">{{ $editingId ? 'Edit tariff #' . $editingId : 'New tariff' }}</div>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block">
                    Supply group *
                    <select wire:model="supply_group_id" class="w-full border rounded px-2 py-1">
                        <option value="">— select —</option>
                        @foreach ($this->supplyGroups as $sg)
                            <option value="{{ $sg->id }}">{{ $sg->code }} — {{ $sg->name }}</option>
                        @endforeach
                    </select>
                    @error('supply_group_id') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Name *
                    <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
                    @error('name') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Code
                    <input type="text" wire:model="code" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block">
                    Rate / kWh *
                    <input type="number" step="0.0001" min="0" wire:model="rate_per_kwh" class="w-full border rounded px-2 py-1 font-mono">
                    @error('rate_per_kwh') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Currency *
                    <input type="text" maxlength="3" wire:model="currency" class="w-full border rounded px-2 py-1 uppercase">
                    @error('currency') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Max power limit (W) <span class="text-gray-400 text-xs">Class 2/0</span>
                    <input type="number" min="0" wire:model="max_power_limit_w" class="w-full border rounded px-2 py-1">
                </label>
                <label class="flex items-center gap-2 mt-5">
                    <input type="checkbox" wire:model="is_active"> Active
                </label>
                <div class="md:col-span-3 flex gap-2 justify-end pt-2 border-t">
                    <button type="button" wire:click="cancel" class="border rounded px-3 py-1.5 text-sm">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white rounded px-4 py-1.5 text-sm">{{ $editingId ? 'Update' : 'Create' }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white border rounded shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">Supply group</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2 text-right">Rate / kWh</th>
                    <th class="px-3 py-2">Cur</th>
                    <th class="px-3 py-2 text-right">Max W</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tariffs as $t)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono">{{ $t->supplyGroup?->code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $t->name }}</td>
                        <td class="px-3 py-2">{{ $t->code ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $t->rate_per_kwh, 4) }}</td>
                        <td class="px-3 py-2">{{ $t->currency }}</td>
                        <td class="px-3 py-2 text-right">{{ $t->max_power_limit_w ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $t->is_active,
                                'bg-gray-200 text-gray-700' => !$t->is_active,
                            ])>{{ $t->is_active ? 'active' : 'inactive' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $t->id }})" class="text-blue-600">Edit</button>
                            <button wire:click="delete({{ $t->id }})" wire:confirm="Delete tariff {{ $t->name }}?" class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">No tariffs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $tariffs->links() }}</div>
</div>
