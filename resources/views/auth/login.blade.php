<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — Plant Coaction Affiliate AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f3f6f4] flex items-center justify-center p-4">
    <div class="w-full max-w-md panel p-7 space-y-6">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-forest-700">
                <span class="w-2 h-2 rounded-full bg-forest-600"></span>
                Plant Coaction Studio
            </div>
            <h1 class="text-xl font-bold text-slate-900">Plant Coaction Affiliate AI</h1>
            <p class="text-sm text-slate-500">Masuk untuk mengelola alur produksi konten affiliate TikTok @plant.coaction.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       placeholder="owner@plantcoaction.local"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Kata Sandi</label>
                <input id="password" type="password" name="password" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="inline-flex items-center gap-2 text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-forest-700 focus:ring-forest-600">
                    Ingat sesi ini
                </label>
                <span class="text-xs text-slate-400">Zona waktu: WIB</span>
            </div>
            <button type="submit" class="btn-primary w-full py-2.5">Masuk ke Studio</button>
        </form>
    </div>
</body>
</html>
