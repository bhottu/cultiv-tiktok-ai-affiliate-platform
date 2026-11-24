@extends('layouts.app')

@section('title', 'Pengaturan Umum, Queue & Scheduler')
@section('header', 'Pengaturan Umum, Queue & Scheduler')
@section('subheader', 'Konfigurasi operasional lokal Windows, zona waktu Asia/Jakarta, dan kesehatan antrean')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
    <div class="lg:col-span-7 panel p-5 space-y-4">
        <h2 class="text-sm font-semibold text-slate-900">Identitas &amp; Mode Operasional</h2>
        <form method="POST" action="{{ route('settings.general.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Nama Brand</label>
                <input type="text" name="brand_name" value="{{ old('brand_name', $brandName) }}" required
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-forest-600 focus:ring-forest-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Target Akun TikTok</label>
                <input type="text" value="{{ $tiktokHandle }}" disabled
                       class="w-full rounded-lg border-slate-200 bg-slate-50 text-slate-500 text-sm">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Mode Operasional (Fase 1)</label>
                    <select name="operational_mode" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="semi_automatic" @selected($operationalMode === 'semi_automatic')>Semi-Automatic (Rekomendasi)</option>
                        <option value="manual" @selected($operationalMode === 'manual')>Manual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Target Video per Hari</label>
                    <input type="number" name="daily_target" min="1" max="3" value="{{ old('daily_target', $dailyTarget) }}" required
                           class="w-full rounded-lg border-slate-300 text-sm">
                </div>
            </div>
            <button type="submit" class="btn-primary">Simpan Pengaturan Umum</button>
        </form>
    </div>

    <div class="lg:col-span-5 panel p-5 space-y-4">
        <h2 class="text-sm font-semibold text-slate-900">Status Queue &amp; Scheduler</h2>
        <div class="text-xs space-y-2 divide-y divide-slate-100">
            <div class="py-2 flex justify-between"><span class="text-slate-500">Queue Connection</span><span class="font-mono font-medium">{{ $queueStats['driver'] }}</span></div>
            <div class="py-2 flex justify-between"><span class="text-slate-500">Job Tertunda</span><span class="font-semibold">{{ $queueStats['pending'] }}</span></div>
            <div class="py-2 flex justify-between"><span class="text-slate-500">Job Gagal</span><span class="font-semibold">{{ $queueStats['failed'] }}</span></div>
            <div class="py-2 flex justify-between"><span class="text-slate-500">Zona Waktu</span><span class="font-mono">{{ $timezone }}</span></div>
            <div class="py-2 flex justify-between"><span class="text-slate-500">Heartbeat Terakhir</span><span>{{ $heartbeat['checked_at_wib'] ?? 'Belum ada' }}</span></div>
        </div>

        <form method="POST" action="{{ route('settings.general.health-check') }}">
            @csrf
            <button type="submit" class="btn-secondary w-full text-xs">Kirim Job Health Check ke Queue</button>
        </form>

        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-[11px] text-slate-600 space-y-1">
            <div class="font-semibold text-slate-800">Perintah Worker &amp; Scheduler (Windows):</div>
            <div class="font-mono">php artisan queue:work --tries=3</div>
            <div class="font-mono">php artisan schedule:work</div>
        </div>
    </div>
</div>
@endsection
