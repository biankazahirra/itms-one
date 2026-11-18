<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-app text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-medium">
        Lewati ke konten
    </a>

    <div class="min-h-full">
        <aside class="fixed inset-y-0 left-0 hidden w-60 border-r border-line bg-surface lg:block">
            <div class="flex h-16 items-center border-b border-line px-5">
                <span class="text-lg font-semibold tracking-tight text-primary">ITMS One</span>
            </div>
            <nav class="space-y-1 p-3" aria-label="Navigasi utama">
                <a href="{{ route('dashboard') }}"
                   @if (request()->routeIs('dashboard')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Dashboard
                </a>

                <a href="{{ route('purchase-requests.index') }}"
                   @if (request()->routeIs('purchase-requests.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('purchase-requests.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Purchase Requisition
                </a>

                <a href="{{ route('rab.index') }}"
                   @if (request()->routeIs('rab.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('rab.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    RAB
                </a>

                <a href="{{ route('contracts.index') }}"
                   @if (request()->routeIs('contracts.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('contracts.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Kontrak
                </a>

                <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-muted">Master Data</p>

                <a href="{{ route('master.vendors.index') }}"
                   @if (request()->routeIs('master.vendors.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('master.vendors.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Vendor
                </a>

                <a href="{{ route('master.units.index') }}"
                   @if (request()->routeIs('master.units.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('master.units.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Satuan
                </a>

                <a href="{{ route('master.departments.index') }}"
                   @if (request()->routeIs('master.departments.*')) aria-current="page" @endif
                   class="flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ease-out {{ request()->routeIs('master.departments.*') ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-app hover:text-ink' }}">
                    Departemen
                </a>
            </nav>
        </aside>

        <div class="lg:pl-60">
            <header class="flex h-16 items-center justify-between border-b border-line bg-surface px-5">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-semibold tracking-tight text-primary lg:hidden">ITMS One</span>
                    <h1 class="text-xl font-semibold tracking-tight">@yield('title', 'Dashboard')</h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-muted">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium transition-[background-color,transform] duration-150 ease-out hover:bg-app active:scale-[0.97]">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <main id="main" class="mx-auto max-w-6xl px-8 py-5">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
