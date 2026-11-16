<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-app px-4 py-10 text-ink antialiased">
    <main class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-primary">ITMS One</h1>
            <p class="mt-1 text-sm text-muted">Sistem Administrasi ITMS</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}"
              class="space-y-5 rounded-xl border border-line bg-surface p-6 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            @csrf

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium">Nama Pengguna</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username"
                       @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                       class="h-10 w-full rounded-lg border border-line bg-surface px-3 text-sm transition-colors duration-150 placeholder:text-muted/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                @error('username')
                    <p id="username-error" role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium">Kata Sandi</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="h-10 w-full rounded-lg border border-line bg-surface px-3 text-sm transition-colors duration-150 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                @error('password')
                    <p id="password-error" role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-primary focus:ring-primary/20">
                Ingat saya
            </label>

            <button type="submit"
                    class="h-10 w-full rounded-lg bg-primary text-sm font-medium text-white transition-[background-color,transform] duration-150 ease-out hover:bg-primary-hover active:scale-[0.98]">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-muted">Akses terbatas untuk pengguna ITMS.</p>
    </main>
</body>
</html>
