<div class="max-w-7xl mx-auto p-4">
    @if (session('sts.flash'))
        <div class="mb-3 bg-green-100 border border-green-300 text-green-800 text-sm rounded px-3 py-2">{{ session('sts.flash') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
        <h1 class="text-xl font-semibold">Tokens</h1>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search token_no, kind..." class="border rounded px-2 py-1 text-sm w-72">
            <select wire:model.live="meterFilter" class="border rounded px-2 py-1 text-sm">
                <option value="">All meters</option>
                @foreach ($this->meters as $m)
                    <option value="{{ $m->id }}">{{ $m->pan }}{{ $m->customer ? ' — ' . $m->customer->name : '' }}</option>
                @endforeach
            </select>
            <button wire:click="checkEngine" class="border rounded px-3 py-1.5 text-sm">
                Engine:
                @if ($engineHealthy === true)
                    <span class="text-green-700 font-semibold">healthy</span>
                @elseif ($engineHealthy === false)
                    <span class="text-red-700 font-semibold">down</span>
                @else
                    <span class="text-gray-500">?</span>
                @endif
            </button>
            <button wire:click="newToken" class="bg-blue-600 text-white text-sm rounded px-3 py-1.5">+ Issue token</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border rounded shadow-sm p-4 mb-4">
            <div class="font-semibold mb-3">Issue Transfer-Electricity-Credit token (Class 0, sub 0)</div>
            <form wire:submit.prevent="issue" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <label class="block md:col-span-2">
                    Meter *
                    <select wire:model="meter_id" class="w-full border rounded px-2 py-1">
                        <option value="">— select active meter with vending key —</option>
                        @foreach ($this->meters as $m)
                            <option value="{{ $m->id }}">{{ $m->pan }}{{ $m->customer ? ' — ' . $m->customer->name : '' }}</option>
                        @endforeach
                    </select>
                    @error('meter_id') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Amount (kWh) *
                    <input type="number" step="0.001" min="0.001" wire:model="amount_kwh" class="w-full border rounded px-2 py-1 font-mono">
                    @error('amount_kwh') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
                </label>
                <label class="block">
                    Random no (0-15)
                    <input type="number" min="0" max="15" wire:model="random_no" class="w-full border rounded px-2 py-1">
                </label>
                <div class="md:col-span-3 flex gap-2 justify-end pt-2 border-t">
                    <button type="button" wire:click="cancel" class="border rounded px-3 py-1.5 text-sm">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white rounded px-4 py-1.5 text-sm">Issue</button>
                </div>
            </form>

            @if ($lastTokenNo)
                <div class="mt-3 p-3 bg-green-50 border border-green-300 rounded">
                    <div class="text-xs text-gray-600">Token issued:</div>
                    <div class="font-mono text-2xl tracking-widest">{{ $lastTokenNo }}</div>
                </div>
            @endif
            @if ($lastError)
                <div class="mt-3 p-3 bg-red-50 border border-red-300 rounded text-sm text-red-800">
                    Engine error: <code>{{ $lastError }}</code>
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white border rounded shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-3 py-2">Issued at</th>
                    <th class="px-3 py-2">Token #</th>
                    <th class="px-3 py-2">Meter PAN</th>
                    <th class="px-3 py-2">Kind</th>
                    <th class="px-3 py-2 text-right">Amount kWh</th>
                    <th class="px-3 py-2">Issuer</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tokens as $t)
                    <tr class="border-t">
                        <td class="px-3 py-2 text-gray-600">{{ $t->issued_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2 font-mono tracking-wider">{{ $t->token_no }}</td>
                        <td class="px-3 py-2 font-mono">{{ $t->meter?->pan ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $t->token_kind }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $t->amount_kwh ? number_format((float) $t->amount_kwh, 4) : '—' }}</td>
                        <td class="px-3 py-2">{{ $t->issuer?->name ?? 'system' }}</td>
                        <td class="px-3 py-2">
                            <span @class([
                                'text-xs rounded px-2 py-0.5',
                                'bg-green-100 text-green-700' => $t->status === 'issued',
                                'bg-yellow-100 text-yellow-700' => $t->status === 'reversed',
                                'bg-red-100 text-red-700' => $t->status === 'failed',
                            ])>{{ $t->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No tokens issued yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $tokens->links() }}</div>
</div>
