<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <nav class="bg-white border-b px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-6">
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name') }}</a>
            <a href="{{ route('supply-groups.index') }}" class="text-sm text-gray-700 hover:text-black">Supply groups</a>
            <a href="{{ route('vending-keys.index') }}" class="text-sm text-gray-700 hover:text-black">Vending keys</a>
            <a href="{{ route('tariffs.index') }}" class="text-sm text-gray-700 hover:text-black">Tariffs</a>
            <a href="{{ route('customers.index') }}" class="text-sm text-gray-700 hover:text-black">Customers</a>
            <a href="{{ route('meters.index') }}" class="text-sm text-gray-700 hover:text-black">Meters</a>
            <a href="{{ route('tokens.index') }}" class="text-sm text-gray-700 hover:text-black">Tokens</a>
        </div>
        <div class="text-sm text-gray-500">
            @auth
                {{ auth()->user()->name }}
                <form method="POST" action="{{ url('/logout') }}" class="inline ml-2">
                    @csrf
                    <button type="submit" class="text-gray-500 hover:text-black">Sign out</button>
                </form>
            @else
                <span class="text-gray-400">guest</span>
            @endauth
        </div>
    </nav>

    <main class="py-4">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
