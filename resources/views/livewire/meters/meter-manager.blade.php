<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">{{ session('sts.flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Meters</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search PAN, IIN, IAIN, location..." class="border rounded px-2 py-1 text-sm w-80">
            <select wire:model.live="supplyGroupFilter" class="border rounded px-2 py-1 text-sm">
                <option value="">All supply groups</option>
                @foreach ($this->supplyGroups as $sg)
                    <option value="{{ $sg->id }}">{{ $sg->code }} — {{ $sg->name }}</option>
                @endforeach
            </select>
            <button wire:click="newMeter" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ New meter</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">{{ $editingId ? 'Edit meter #' . $editingId : 'New meter' }}</div>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block">
                    PAN * <span class="text-gray-400 text-xs">(18 digits)</span>
                    <input type="text" maxlength="18" wire:model="pan" class="w-full border rounded px-2 py-1 font-mono">
                    @error('pan') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    IIN * <span class="text-gray-400 text-xs">(6 digits)</span>
                    <input type="text" maxlength="6" wire:model="iin" class="w-full border rounded px-2 py-1 font-mono">
                    @error('iin') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    IAIN * <span class="text-gray-400 text-xs">(11-13 digits)</span>
                    <input type="text" maxlength="13" wire:model="iain" class="w-full border rounded px-2 py-1 font-mono">
                    @error('iain') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Manufacturer code
                    <input type="text" maxlength="4" wire:model="manufacturer_code" class="w-full border rounded px-2 py-1 font-mono">
                </label>
                <label class="block">
                    Decoder serial number
                    <input type="text" maxlength="8" wire:model="decoder_serial_number" class="w-full border rounded px-2 py-1 font-mono">
                </label>
                <label class="block">
                    Installed at
                    <input type="date" wire:model="installed_at" class="w-full border rounded px-2 py-1">
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
                    Vending key
                    <select wire:model="vending_key_id" class="w-full border rounded px-2 py-1">
                        <option value="">— none —</option>
                        @foreach ($this->vendingKeys as $k)
                            <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->algorithm }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    Tariff
                    <select wire:model="tariff_id" class="w-full border rounded px-2 py-1">
                        <option value="">— none —</option>
                        @foreach ($this->tariffs as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ number_format((float) $t->rate_per_kwh, 4) }} {{ $t->currency }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    Customer
                    <select wire:model="customer_id" class="w-full border rounded px-2 py-1">
                        <option value="">— none —</option>
                        @foreach ($this->customers as $c)
                            <option value="{{ $c->id }}">{{ $c->account_number }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block md:col-span-2">
                    Location
                    <input type="text" wire:model="location" class="w-full border rounded px-2 py-1">
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
                    <th class="px-3 py-2">PAN</th>
                    <th class="px-3 py-2">SG</th>
                    <th class="px-3 py-2">Vending key</th>
                    <th class="px-3 py-2">Tariff</th>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Location</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meters as $m)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono">{{ $m->pan }}</td>
                        <td class="px-3 py-2 font-mono">{{ $m->supplyGroup?->code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $m->vendingKey?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $m->tariff?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $m->customer?->account_number ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $m->location ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $m->is_active,
                                'bg-gray-200 text-gray-700' => !$m->is_active,
                            ])>{{ $m->is_active ? 'active' : 'inactive' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $m->id }})" class="text-blue-600">Edit</button>
                            <button wire:click="delete({{ $m->id }})" wire:confirm="Delete meter {{ $m->pan }}? Cascades to its tokens." class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">No meters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $meters->links() }}</div>
</div>
