@extends('layouts.app')

@section('title', 'Produksi Naskah & Campaign')
@section('header', 'Produksi Naskah, Caption & Campaign')
@section('subheader', 'Alur kerja Draft → Menunggu Persetujuan → Disetujui')

@section('content')
<div class="panel p-6 space-y-3">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Status Mesin Generasi Konten</h2>
            <p class="text-xs text-slate-500">Campaign aktif: {{ $campaigns->count() }} · Konten tersimpan: {{ $contents->count() }}</p>
        </div>
        <span class="{{ $aiReady ? 'badge-ok' : 'badge-warn' }}">
            {{ $aiReady ? 'OpenRouter Terkonfigurasi' : 'Menunggu API Key OpenRouter' }}
        </span>
    </div>
    <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
        Belum ada naskah atau campaign yang digenerate. Konfigurasikan OpenRouter pada menu <a href="{{ route('settings.openrouter.index') }}" class="text-forest-700 underline">AI Provider</a> untuk persiapan Fase 4.
    </div>
</div>
@endsection
