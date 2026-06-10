<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">
            {{ session('sts.flash') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Supply groups</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Search code, name, utility, region..."
                   class="border rounded px-2 py-1 text-sm w-80">
            <button wire:click="newSupplyGroup" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ New supply group</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">
                {{ $editingId ? 'Edit supply group #' . $editingId : 'New supply group' }}
            </div>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block">
                    SGC * <span class="text-gray-400 text-xs">(6 digits)</span>
                    <input type="text" maxlength="6" wire:model="code" class="w-full border rounded px-2 py-1 font-mono">
                    @error('code') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block md:col-span-2">
                    Name *
                    <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
                    @error('name') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Utility
                    <input type="text" wire:model="utility" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block">
                    Region
                    <input type="text" wire:model="region" class="w-full border rounded px-2 py-1">
                </label>
                <label class="flex items-center gap-2 mt-5">
                    <input type="checkbox" wire:model="is_active"> Active
                </label>

                <div class="md:col-span-3 flex gap-2 justify-end pt-2 border-t">
                    <button type="button" wire:click="cancel" class="border rounded px-3 py-1.5 text-sm">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white rounded px-4 py-1.5 text-sm">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white border rounded shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">SGC</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Utility</th>
                    <th class="px-3 py-2">Region</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplyGroups as $sg)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono">{{ $sg->code }}</td>
                        <td class="px-3 py-2">{{ $sg->name }}</td>
                        <td class="px-3 py-2">{{ $sg->utility ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $sg->region ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $sg->is_active,
                                'bg-gray-200 text-gray-700' => !$sg->is_active,
                            ])>
                                {{ $sg->is_active ? 'active' : 'inactive' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $sg->id }})" class="text-blue-600">Edit</button>
                            <button wire:click="delete({{ $sg->id }})"
                                    wire:confirm="Delete supply group {{ $sg->code }}? Cascades to vending keys, meters and tariffs."
                                    class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">No supply groups.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $supplyGroups->links() }}</div>
</div>
