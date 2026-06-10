<div class="bg-white border rounded shadow-sm p-4 text-sm"
     data-tour="engine-bridge"
     wire:poll.10s="refresh">
    <div class="flex items-center justify-between mb-2">
        <div class="font-semibold">Engine bridge</div>
        <div class="flex items-center gap-2">
            @if ($status === 'ok')
                <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                <span class="text-green-700 font-medium text-xs uppercase tracking-wide">Online</span>
            @elseif ($status === 'error')
                <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                <span class="text-red-700 font-medium text-xs uppercase tracking-wide">Offline</span>
            @else
                <span class="inline-block w-2 h-2 rounded-full bg-gray-300 animate-pulse"></span>
                <span class="text-gray-500 font-medium text-xs uppercase tracking-wide">Checking…</span>
            @endif
            <button type="button" wire:click="refresh" wire:loading.attr="disabled"
                    class="text-xs text-gray-500 hover:text-black underline ml-2">
                <span wire:loading.remove wire:target="refresh">Recheck</span>
                <span wire:loading wire:target="refresh">Checking…</span>
            </button>
        </div>
    </div>

    <div class="text-gray-600 space-y-0.5">
        <div>URL: <code>{{ $url }}</code></div>
        <div>Bearer: <code>{{ $bearerPreview }}</code></div>

        @if ($status === 'ok')
            <div class="text-xs text-gray-500">
                service: <code>{{ $service }}</code>
                @if ($latencyMs !== null) · {{ $latencyMs }} ms @endif
                @if ($checkedAt) · last check {{ $checkedAt }} @endif
            </div>
        @elseif ($status === 'error')
            <div class="text-xs text-red-600">
                {{ $error }}@if ($checkedAt) · last check {{ $checkedAt }} @endif
            </div>
            @if ($error === 'connection refused')
                <div class="mt-1 text-xs text-gray-500">
                    Start it with <code>dart run bin/server.dart</code> in
                    <code>{{ env('STS_DART_PROJECT', 'C:\www\dart\nectar_sts_dart') }}</code>.
                </div>
            @endif
        @endif
    </div>
</div>
