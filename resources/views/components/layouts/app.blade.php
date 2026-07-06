@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-dkj.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-dkj.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&family=jetbrains-mono:400,500&family=fraunces:opsz,wght@9..144,300..900" rel="stylesheet" />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @stack('scripts')
</head>
<body class="min-h-screen bg-gray-50 antialiased font-sans">

    <x-ui.layout variant="sidebar-main">
        {{-- Sidebar (white theme) --}}
        <x-ui.sidebar>
            <x-slot:brand>
                <a href="/" class="flex items-center gap-3 px-2 py-2" wire:navigate>
                    <img src="{{ asset('images/logo-dkj.png') }}" alt="DKJ" class="h-9 w-9 shrink-0 object-contain" />
                    <div data-slot="brand-name">
                        <div style="font-size:15px; font-weight:800; letter-spacing:-0.025em;">
                            <span style="color:#1E293B;">IT</span> <span style="color:#0E4260;">HelpDesk</span>
                        </div>
                        <div style="font-size:10px; color:#64748B;">PT. Dunia Kimia Jaya</div>
                    </div>
                </a>
            </x-slot:brand>

            {{-- Navigation --}}
            <nav class="flex flex-col gap-1 px-3 py-4 flex-1">
                @auth
                    <a href="/" @click.stop class="sidebar-nav-link {{ request()->is('/') ? 'active' : '' }}" wire:navigate>
                        <x-ui.icon name="squares-2x2" class="size-[18px]" />
                        <span class="[:has([data-collapsed]_&)_&]:hidden">Dashboard</span>
                    </a>

                    @if(auth()->user()->isManager())
                        <a href="/manager/master-data" @click.stop class="sidebar-nav-link {{ request()->is('manager/master-data') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="circle-stack" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">Master Data</span>
                        </a>
                        <a href="/manager/people" @click.stop class="sidebar-nav-link {{ request()->is('manager/people') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="users" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">People</span>
                        </a>
                        <a href="/manager/oversight" @click.stop class="sidebar-nav-link {{ request()->is('manager/oversight') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="scale" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">Oversight</span>
                        </a>
                        <a href="/manager/all-tickets" @click.stop class="sidebar-nav-link {{ request()->is('manager/all-tickets') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="ticket" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">All Tickets</span>
                        </a>
                    @endif

                    @if(auth()->user()->isKaryawan())
                        <a href="/my-tickets" @click.stop class="sidebar-nav-link {{ request()->is('my-tickets') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="ticket" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">My Tickets</span>
                        </a>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <a href="/admin/all-tickets" @click.stop class="sidebar-nav-link {{ request()->is('admin/all-tickets') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="ticket" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">All Tickets</span>
                        </a>
                        <a href="/admin/master-data" @click.stop class="sidebar-nav-link {{ request()->is('admin/master-data') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="circle-stack" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">Master Data</span>
                        </a>
                        <a href="/admin/people" @click.stop class="sidebar-nav-link {{ request()->is('admin/people') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="users" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">People</span>
                        </a>
                    @endif

                    <a href="/alerts" @click.stop class="sidebar-nav-link {{ request()->is('alerts') ? 'active' : '' }}" wire:navigate>
                        <x-ui.icon name="bell" class="size-[18px]" />
                        <span class="[:has([data-collapsed]_&)_&]:hidden">Alerts</span>
                    </a>

                    @if(auth()->user()->hasAdminAccess())
                        <a href="/admin/activity-log" @click.stop class="sidebar-nav-link {{ request()->is('admin/activity-log') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="clipboard-document-list" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">Activity Log</span>
                        </a>
                    @endif
                    @if(auth()->user()->hasAdminAccess())
                        <a href="/admin/report" @click.stop class="sidebar-nav-link {{ request()->is('admin/report') ? 'active' : '' }}" wire:navigate>
                            <x-ui.icon name="chart-bar" class="size-[18px]" />
                            <span class="[:has([data-collapsed]_&)_&]:hidden">Report</span>
                        </a>
                    @endif
                @endauth
            </nav>
        </x-ui.sidebar>

        {{-- Main Content --}}
        <x-ui.layout.main class="flex flex-col h-screen">
            {{-- Top Navbar --}}
            @auth
                <header class="app-navbar shrink-0">
                    {{-- Left: Hamburger (mobile) + Page title --}}
                    <div class="flex items-center gap-3">
                        <button
                            x-data
                            @click="
                                let layout = document.querySelector('[data-slot=layout]');
                                if (layout && layout._x_dataStack) {
                                    layout._x_dataStack[0].toggle();
                                }
                            "
                            class="p-2 rounded-lg hover:bg-gray-100 transition-colors md:hidden"
                            aria-label="Toggle sidebar"
                        >
                            <x-ui.icon name="bars-3" class="size-5" style="color:#1E293B;" />
                        </button>
                        <div>
                            <h1 class="text-lg font-bold" style="color:#1E293B;">{{ $title ?? 'Dashboard' }}</h1>
                            @if($description)
                                <p class="text-xs mt-0.5" style="color:#64748B;">{{ $description }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Right: Notifications + Profile --}}
                    <div class="flex items-center gap-4">
                        {{-- Notification bell (Livewire component, real-time via Reverb + Livewire events) --}}
                        <livewire:bell-badge />


                        {{-- Profile badge + dropdown --}}
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="profile-badge">
                                @if(auth()->user()->avatarUrl())
                                    <img src="{{ auth()->user()->avatarUrl() }}" class="h-8 w-8 rounded-full object-cover" alt="Avatar" />
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-[11px] font-bold text-white" style="background:#0E4260;">
                                        {{ auth()->user()->initials() }}
                                    </div>
                                @endif
                                <div class="text-left hidden sm:block">
                                    <div class="text-[13px] font-semibold" style="color:#1E293B;">{{ auth()->user()->name }}</div>
                                    <div class="text-[11px] capitalize" style="color:#64748B;">{{ auth()->user()->peran }}</div>
                                </div>
                                <x-ui.icon name="chevron-down" class="size-4 hidden sm:block" style="color:#94A3B8;" />
                            </button>

                            {{-- Dropdown --}}
                            <div
                                x-show="open"
                                @click.outside="open = false"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="profile-dropdown"
                                style="display: none;"
                            >
                                @livewire('profile-popup')
                            </div>
                        </div>
                    </div>
                </header>
            @endauth

            <main class="flex-1 min-h-0 overflow-y-auto flex flex-col">
                <div class="mx-auto max-w-[1600px] w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8 animate-fade-in flex-1 min-h-0 flex flex-col">
                    {{ $slot }}
                </div>
            </main>

            {{-- Global Footer --}}
            <footer class="shrink-0 border-t border-gray-200 bg-white px-6 py-2.5 text-[11px] text-gray-400 text-center">
                © {{ date('Y') }} PT. Dunia Kimia Jaya · IT Helpdesk
            </footer>
        </x-ui.layout.main>
    </x-ui.layout>

    <x-ui.toast position="top-right" />
    
    @livewireScripts
</body>
</html>
