<div>
    @if (! $hasData)
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded p-4 text-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-semibold mb-1">First-time setup</div>
                    <p class="text-amber-800">
                        Your database has no supply groups, vending keys, or meters yet.
                        Run <code class="bg-amber-100 px-1 rounded">php&nbsp;artisan&nbsp;sts:setup</code>
                        to auto-sync sample keys between Laravel and the Dart engine and seed
                        a working demo (admin user, supply group, vending key, tariff,
                        customer, and meter ready to vend).
                    </p>
                    @if ($allowed)
                        <p class="text-amber-800 mt-2">
                            Or just click below to run <code>db:seed</code> in-browser
                            (sample keys still need to be synced via the artisan command
                            if the Dart server is running with a different key).
                        </p>
                    @endif
                </div>
                @if ($allowed)
                    <button type="button"
                            wire:click="seed"
                            wire:loading.attr="disabled"
                            wire:target="seed"
                            class="shrink-0 bg-amber-600 text-white px-3 py-1.5 rounded text-xs font-semibold hover:bg-amber-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="seed">Seed demo data</span>
                        <span wire:loading wire:target="seed">Seeding…</span>
                    </button>
                @endif
            </div>
            @if ($message)
                <div class="mt-3 text-xs text-amber-900 bg-amber-100 rounded px-2 py-1">
                    {{ $message }}
                </div>
            @endif
        </div>
    @elseif ($message)
        <div class="bg-green-50 border border-green-300 text-green-900 rounded p-3 text-sm">
            {{ $message }}
        </div>
    @endif
</div>
