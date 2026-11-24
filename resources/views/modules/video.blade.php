@extends('layouts.app')

@section('title', 'Studio Video Remotion')
@section('header', 'Studio Video Vertikal 9:16 (Remotion + FFmpeg)')
@section('subheader', 'Pemeriksaan kesiapan lingkungan rendering lokal Windows')

@section('content')
<div class="panel p-5 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Status Kesiapan Rendering Lokal</h2>
        <span class="{{ $readiness['ready'] ? 'badge-ok' : 'badge-muted' }}">{{ $readiness['ready'] ? 'Siap Render' : 'Dijadwalkan Fase 5' }}</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="rounded-lg border border-slate-200 p-3">
            <div class="text-slate-500">Node.js Runtime</div>
            <div class="font-semibold mt-1 {{ $readiness['node'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $readiness['node'] ? 'Terdeteksi' : 'Tidak Ditemukan' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 p-3">
            <div class="text-slate-500">FFmpeg Binary</div>
            <div class="font-semibold mt-1 {{ $readiness['ffmpeg'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $readiness['ffmpeg'] ? 'Terdeteksi' : 'Tidak Ditemukan' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 p-3">
            <div class="text-slate-500">Paket Remotion Lokal</div>
            <div class="font-semibold mt-1 text-slate-700">{{ $readiness['remotion_installed'] ? 'Terpasang' : 'Belum Diinstal (Fase 5)' }}</div>
        </div>
    </div>
    <p class="text-xs text-slate-600">{{ $readiness['message'] }}</p>
</div>
@endsection
