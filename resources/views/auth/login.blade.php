<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-dkj.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-dkj.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased font-sans">

    {{-- Full-screen background --}}
    <div class="relative min-h-screen flex items-center justify-center px-4 py-12"
         style="background: url('{{ asset('images/login-bg.png') }}') center/cover no-repeat;">

        {{-- Dark overlay --}}
        <div class="absolute inset-0 bg-black/60"></div>

        {{-- Login card --}}
        <div class="relative w-full max-w-sm animate-slide-up z-10">
            <div class="rounded-2xl border border-white/10 bg-white/95 backdrop-blur-xl shadow-2xl overflow-hidden">
                {{-- Header --}}
                <div class="bg-dongker-600 px-6 py-6 text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-xl bg-white/15">
                        <img src="{{ asset('images/logo-dkj.png') }}" alt="DKJ" class="h-9 w-9 object-contain" />
                    </div>
                    <h2 class="text-lg font-bold text-white">IT HelpDesk</h2>
                    <p class="text-sm text-white/50 mt-1">Sign in to continue</p>
                </div>

                {{-- Form --}}
                <div class="p-6">
                    <form method="POST" action="/login" class="space-y-4">
                        @csrf

                        <x-ui.field>
                            <x-ui.label for="email" class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">Email</x-ui.label>
                            <x-ui.input
                                type="email"
                                name="email"
                                id="email"
                                placeholder="admin@ithelp.local"
                                :invalid="$errors->has('email')"
                                value="{{ old('email') }}"
                                required
                                autofocus
                            />
                            @error('email')
                                <x-ui.error class="animate-shake">{{ $message }}</x-ui.error>
                            @enderror
                        </x-ui.field>

                        <x-ui.field>
                            <x-ui.label for="password" class="!text-[12px] !font-semibold !uppercase !tracking-wider !text-dongker-500">Password</x-ui.label>
                            <x-ui.input
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Enter your password"
                                :invalid="$errors->has('password')"
                                required
                            />
                            @error('password')
                                <x-ui.error class="animate-shake">{{ $message }}</x-ui.error>
                            @enderror
                        </x-ui.field>

                        <button
                            type="submit"
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-dongker-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-dongker-600/25 transition-all duration-200 hover:bg-dongker-500 hover:shadow-xl hover:-translate-y-0.5 active:scale-95"
                        >
                            Sign In
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-[11px] text-white/30 mt-6">
                &copy; {{ date('Y') }} IT Help &middot; PT. Dunia Kimia Jaya
            </p>
        </div>
    </div>

</body>
</html>
