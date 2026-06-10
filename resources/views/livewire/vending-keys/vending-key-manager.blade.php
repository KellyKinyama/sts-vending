<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">{{ session('sts.flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Vending keys</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, algorithm..." class="border rounded px-2 py-1 text-sm w-72">
            <select wire:model.live="supplyGroupFilter" class="border rounded px-2 py-1 text-sm">
                <option value="">All supply groups</option>
                @foreach ($this->supplyGroups as $sg)
                    <option value="{{ $sg->id }}">{{ $sg->code }} — {{ $sg->name }}</option>
                @endforeach
            </select>
            <button wire:click="newVendingKey" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ New vending key</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">{{ $editingId ? 'Edit vending key #' . $editingId : 'New vending key' }}</div>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block md:col-span-2">
                    Name *
                    <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
                    @error('name') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
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
                    Key type *
                    <select wire:model="key_type" class="w-full border rounded px-2 py-1">
                        @foreach (range(1, 7) as $kt)
                            <option value="{{ $kt }}">{{ $kt }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    Tariff index *
                    <input type="text" maxlength="2" wire:model="tariff_index" class="w-full border rounded px-2 py-1 font-mono">
                    @error('tariff_index') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Key revision (0-15)
                    <input type="number" min="0" max="15" wire:model="key_revision_number" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block">
                    Key expiry (0-255)
                    <input type="number" min="0" max="255" wire:model="key_expiry_number" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block">
                    Algorithm *
                    <select wire:model.live="algorithm" class="w-full border rounded px-2 py-1">
                        <option value="DKGA02">DKGA02</option>
                        <option value="DKGA04">DKGA04</option>
                    </select>
                </label>
                <label class="block">
                    Encryption algorithm *
                    <select wire:model="encryption_algorithm" class="w-full border rounded px-2 py-1">
                        <option value="EA07">EA07 (DES / STA)</option>
                        <option value="EA11">EA11 (MISTY1)</option>
                    </select>
                </label>
                <label class="block">
                    Base date
                    <input type="number" min="1980" max="2100" wire:model="base_date" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block md:col-span-3">
                    VUDK (hex) * <span class="text-gray-400 text-xs">{{ $algorithm === 'DKGA02' ? '16 hex chars (8 bytes)' : '40 hex chars (20 bytes)' }}</span>
                    <input type="text" wire:model="vudk_blob" class="w-full border rounded px-2 py-1 font-mono"
                           placeholder="{{ $algorithm === 'DKGA02' ? 'abababababababab' : str_repeat('ab', 20) }}">
                    @error('vudk_blob') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                    <div class="text-xs text-gray-400 mt-1">Stored encrypted at rest. Visible to admins for rotation.</div>
                </label>
                <label class="flex items-center gap-2 mt-2">
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
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Supply group</th>
                    <th class="px-3 py-2">Algo</th>
                    <th class="px-3 py-2">Enc</th>
                    <th class="px-3 py-2">KT</th>
                    <th class="px-3 py-2">TI</th>
                    <th class="px-3 py-2">KRN</th>
                    <th class="px-3 py-2">KEN</th>
                    <th class="px-3 py-2">BD</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendingKeys as $k)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $k->name }}</td>
                        <td class="px-3 py-2 font-mono">{{ $k->supplyGroup?->code ?? '—' }}</td>
                        <td class="px-3 py-2"><span class="text-xs bg-blue-100 text-blue-700 rounded px-2 py-0.5">{{ $k->algorithm }}</span></td>
                        <td class="px-3 py-2"><span class="text-xs bg-purple-100 text-purple-700 rounded px-2 py-0.5">{{ $k->encryption_algorithm }}</span></td>
                        <td class="px-3 py-2">{{ $k->key_type }}</td>
                        <td class="px-3 py-2 font-mono">{{ $k->tariff_index }}</td>
                        <td class="px-3 py-2">{{ $k->key_revision_number }}</td>
                        <td class="px-3 py-2">{{ $k->key_expiry_number }}</td>
                        <td class="px-3 py-2">{{ $k->base_date }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $k->is_active,
                                'bg-gray-200 text-gray-700' => !$k->is_active,
                            ])>{{ $k->is_active ? 'active' : 'inactive' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $k->id }})" class="text-blue-600">Edit</button>
                            <button wire:click="delete({{ $k->id }})" wire:confirm="Delete vending key {{ $k->name }}? Cascades to its tokens." class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-3 py-6 text-center text-gray-500">No vending keys.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $vendingKeys->links() }}</div>
</div>
