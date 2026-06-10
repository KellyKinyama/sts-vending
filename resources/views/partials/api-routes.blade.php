@php
    use Illuminate\Routing\Route as RoutingRoute;

    $laravelRoutes = collect(app('router')->getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/v1'))
        ->map(fn (RoutingRoute $r) => [
            'methods' => array_values(array_filter($r->methods(), fn ($m) => $m !== 'HEAD')),
            'uri'     => '/'.$r->uri(),
            'name'    => $r->getName() ?: '',
        ])
        ->sortBy(fn ($r) => $r['uri'].' '.implode(',', $r['methods']))
        ->values();

    $dartRoutes = [
        ['GET',    '/healthz',             'liveness probe (no auth)'],
        ['POST',   '/v1/tokens',           'mint an STS token'],
        ['GET',    '/v1/tokens',           'list tokens (?iin=&iain=)'],
        ['GET',    '/v1/tokens/{tokenNo}', 'fetch a single token'],
        ['POST',   '/v1/tokens/{tokenNo}', 'decode a token by number'],
        ['POST',   '/v1/meters',           'register a meter'],
        ['GET',    '/v1/meters',           'list registered meters'],
        ['GET',    '/v1/meters/{serial}',  'fetch a meter by serial'],
        ['DELETE', '/v1/meters/{serial}',  'remove a registered meter'],
    ];

    $laravelBase = rtrim(url('/'), '/').'/api';
    $dartBase    = rtrim((string) config('services.sts_engine.url'), '/');

    $methodColors = [
        'GET'    => 'bg-emerald-100 text-emerald-700',
        'POST'   => 'bg-sky-100 text-sky-700',
        'PUT'    => 'bg-amber-100 text-amber-700',
        'PATCH'  => 'bg-amber-100 text-amber-700',
        'DELETE' => 'bg-rose-100 text-rose-700',
    ];
@endphp

<div class="bg-white border rounded shadow-sm p-4 text-sm" data-tour="apis">
    <div class="flex items-center justify-between mb-3">
        <div class="font-semibold">APIs</div>
        <div class="text-xs text-gray-500">
            {{ $laravelRoutes->count() }} Laravel · {{ count($dartRoutes) }} Dart
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">
                Laravel
                <span class="font-mono normal-case text-gray-400">{{ $laravelBase }}</span>
            </div>
            <ul class="space-y-1 font-mono text-xs">
                @foreach ($laravelRoutes as $r)
                    <li class="flex items-baseline gap-1.5">
                        @foreach ($r['methods'] as $m)
                            <span class="px-1.5 py-0.5 rounded text-[10px] {{ $methodColors[$m] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $m }}
                            </span>
                        @endforeach
                        <span class="text-gray-800 break-all">{{ $r['uri'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">
                Dart engine
                <span class="font-mono normal-case text-gray-400">{{ $dartBase }}</span>
            </div>
            <ul class="space-y-1 font-mono text-xs">
                @foreach ($dartRoutes as [$method, $path, $desc])
                    <li class="flex items-baseline gap-1.5">
                        <span class="px-1.5 py-0.5 rounded text-[10px] {{ $methodColors[$method] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $method }}
                        </span>
                        <span class="text-gray-800 break-all">{{ $path }}</span>
                        <span class="text-gray-400 normal-case">— {{ $desc }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <p class="text-xs text-gray-500 mt-3 leading-relaxed">
        Laravel routes require <code>Authorization: Bearer &lt;sanctum-token&gt;</code>.
        Dart routes require <code>Authorization: Bearer {{ \Illuminate\Support\Str::limit((string) config('services.sts_engine.token'), 8) }}…</code>
        (set by <code>NECTAR_API_TOKEN</code> in the Dart server's <code>.env</code>).
    </p>
</div>
