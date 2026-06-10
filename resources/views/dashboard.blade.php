@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">STS Vending</h1>
        <p class="text-sm text-gray-600">Administer supply groups, vending keys, meters, customers and issue STS tokens.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @php $tiles = [
            ['Supply groups', 'supply-groups.index', 'SGC + utility / region containers'],
            ['Vending keys',  'vending-keys.index',  'VUDK store (DKGA02 / DKGA04, EA07 / EA11)'],
            ['Tariffs',       'tariffs.index',       'Rate per kWh and max-power limits'],
            ['Customers',     'customers.index',     'Billing accounts'],
            ['Meters',        'meters.index',        'PAN-keyed STS meters'],
            ['Tokens',        'tokens.index',        'Issue + audit log of generated tokens'],
        ]; @endphp
        @foreach ($tiles as [$label, $route, $desc])
            <a href="{{ route($route) }}"
               class="block bg-white border rounded shadow-sm p-4 hover:shadow transition">
                <div class="font-semibold">{{ $label }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $desc }}</div>
            </a>
        @endforeach
    </div>

    <div class="bg-white border rounded shadow-sm p-4 text-sm">
        <div class="font-semibold mb-1">Engine bridge</div>
        <div class="text-gray-600">
            URL: <code>{{ config('services.sts_engine.url') }}</code><br>
            Bearer: <code>{{ Str::limit((string) config('services.sts_engine.token'), 8) }}…</code>
        </div>
    </div>
</div>
@endsection
