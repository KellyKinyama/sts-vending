<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">{{ session('sts.flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Customers</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search account, name, phone, email..." class="border rounded px-2 py-1 text-sm w-80">
            <button wire:click="newCustomer" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ New customer</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">{{ $editingId ? 'Edit customer #' . $editingId : 'New customer' }}</div>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block">
                    Account number *
                    <input type="text" wire:model="account_number" class="w-full border rounded px-2 py-1 font-mono">
                    @error('account_number') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block md:col-span-2">
                    Name *
                    <input type="text" wire:model="name" class="w-full border rounded px-2 py-1">
                    @error('name') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Phone
                    <input type="text" wire:model="phone" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block">
                    Email
                    <input type="email" wire:model="email" class="w-full border rounded px-2 py-1">
                    @error('email') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    City
                    <input type="text" wire:model="city" class="w-full border rounded px-2 py-1">
                </label>
                <label class="block md:col-span-3">
                    Address
                    <input type="text" wire:model="address" class="w-full border rounded px-2 py-1">
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
                    <th class="px-3 py-2">Account</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">City</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr class="border-t">
                        <td class="px-3 py-2 font-mono">{{ $c->account_number }}</td>
                        <td class="px-3 py-2">{{ $c->name }}</td>
                        <td class="px-3 py-2">{{ $c->phone ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $c->email ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $c->city ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $c->is_active,
                                'bg-gray-200 text-gray-700' => !$c->is_active,
                            ])>{{ $c->is_active ? 'active' : 'inactive' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $c->id }})" class="text-blue-600">Edit</button>
                            <button wire:click="delete({{ $c->id }})" wire:confirm="Delete customer {{ $c->account_number }}?" class="text-red-600">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No customers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
</div>
