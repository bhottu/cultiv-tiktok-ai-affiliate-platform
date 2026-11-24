@extends('layouts.app')

@section('title', 'Analytics TikTok')
@section('header', 'Analytics Akun TikTok — @plant.coaction')
@section('subheader', 'Menampilkan hanya metrik resmi sesuai scope API yang disetujui (tanpa angka fiktif)')

@section('content')
<div class="space-y-4">
    @if ($analyticsAvailability['basic_profile_stats_available'] && $account)
        @php $stats = $account->profile_stats; @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="panel p-4">
                <div class="text-xs text-slate-500">Followers (API Resmi)</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ isset($stats['follower_count']) ? number_format((int) $stats['follower_count']) : '—' }}</div>
            </div>
            <div class="panel p-4">
                <div class="text-xs text-slate-500">Total Likes (API Resmi)</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ isset($stats['likes_count']) ? number_format((int) $stats['likes_count']) : '—' }}</div>
            </div>
            <div class="panel p-4">
                <div class="text-xs text-slate-500">Video Publik</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ isset($stats['video_count']) ? number_format((int) $stats['video_count']) : '—' }}</div>
            </div>
            <div class="panel p-4">
                <div class="text-xs text-slate-500">Sinkronisasi Terakhir</div>
                <div class="text-xs font-semibold text-slate-800 mt-2">{{ $account->last_synced_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</div>
            </div>
        </div>
    @else
        <div class="panel p-6 text-center space-y-2">
            <span class="badge-muted">Akses Analytics Belum Tersedia</span>
            <h2 class="text-sm font-semibold text-slate-900">Data Analytics TikTok Belum Tersedia Melalui Integrasi Resmi</h2>
            <p class="text-xs text-slate-500 max-w-xl mx-auto">
                Aplikasi tidak menampilkan angka dummy atau estimasi palsu. Hubungkan akun TikTok pada menu <a href="{{ route('tiktok.account') }}" class="text-forest-700 underline">Akun TikTok</a> dengan izin <code>user.info.stats</code> untuk statistik ringkas akun.
            </p>
        </div>
    @endif

    <div class="panel p-5 space-y-3">
        <h3 class="text-sm font-semibold text-slate-900">Kebutuhan Izin &amp; Batasan API Resmi TikTok</h3>
        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5">
            @foreach ($analyticsAvailability['missing_requirements'] as $req)
                <li>{{ $req }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
