@extends('layouts.app')

@section('title', 'Jadwal & Akun TikTok')
@section('header', 'Akun TikTok & Jadwal Publikasi')
@section('subheader', 'Integrasi resmi TikTok Content Posting API (dijadwalkan mulai Fase 2 & 6)')

@section('content')
<div class="panel p-5 space-y-3">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Target Akun: {{ $tiktokStatus['handle'] }}</h2>
            <p class="text-xs text-slate-500">Status OAuth: {{ $tiktokStatus['account_status'] }} · Status TikTok Affiliate: {{ $tiktokStatus['affiliate_status'] }}</p>
        </div>
        <span class="badge-muted">Fase 2 (Belum Terhubung)</span>
    </div>
    <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-xs text-slate-600 space-y-1">
        <p>{{ $tiktokStatus['message'] }}</p>
        <p><strong>Jadwal aktif:</strong> {{ $schedules->count() }} (tidak ada publikasi TikTok nyata yang dijadwalkan pada Fase 1).</p>
    </div>
</div>
@endsection
