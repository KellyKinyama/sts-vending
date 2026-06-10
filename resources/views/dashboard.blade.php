@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">STS Vending</h1>
        <p class="text-sm text-gray-600">Administer supply groups, vending keys, meters, customers and issue STS tokens.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @php $tiles = [
            ['Supply groups', 'supply-groups.index', 'SGC + utility / region containers', 'supply-groups'],
            ['Vending keys',  'vending-keys.index',  'VUDK store (DKGA02 / DKGA04, EA07 / EA11)', 'vending-keys'],
            ['Tariffs',       'tariffs.index',       'Rate per kWh and max-power limits', 'tariffs'],
            ['Customers',     'customers.index',     'Billing accounts', 'customers'],
            ['Meters',        'meters.index',        'PAN-keyed STS meters', 'meters'],
            ['Tokens',        'tokens.index',        'Issue + audit log of generated tokens', 'tokens'],
        ]; @endphp
        @foreach ($tiles as [$label, $route, $desc, $slug])
            <a href="{{ route($route) }}"
               data-tour="tile-{{ $slug }}"
               class="block bg-white border rounded shadow-sm p-4 hover:shadow transition">
                <div class="font-semibold">{{ $label }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $desc }}</div>
            </a>
        @endforeach
    </div>

    <livewire:dashboard.engine-status-card />
</div>
@endsection
