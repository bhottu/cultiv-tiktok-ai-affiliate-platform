@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Plant Coaction Affiliate AI')
@section('subheader', 'Ringkasan alur produksi konten affiliate TikTok @plant.coaction (data aktual database)')

@section('content')
@php
    $counts = $overview['counts'];
    $ai = $overview['providers']['ai'];
    $tts = $overview['providers']['tts'];
    $queue = $overview['providers']['queue'];
    $video = $overview['providers']['video'];
    $tiktok = $overview['providers']['tiktok'];
@endphp

<!-- Compact KPI Strip (6 metrik wajib dari database aktual) -->
<section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Total Ide Konten</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['content_ideas'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Belum ada ide tersimpan</div>
    </div>
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Konten Draft</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['draft_contents'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Status: draft</div>
    </div>
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Menunggu Persetujuan</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['pending_approval'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Review manual aktif</div>
    </div>
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Video Selesai Render</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['rendered_videos'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Remotion 9:16</div>
    </div>
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Jadwal Publikasi</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['scheduled_publications'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Target: 1 video/hari</div>
    </div>
    <div class="panel px-4 py-3.5">
        <div class="text-xs text-slate-500">Produk Terverifikasi</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $counts['verified_products'] }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Approved TikTok Shop</div>
    </div>
</section>

<!-- Status Provider & Infrastruktur -->
<section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- AI Provider -->
    <div class="panel p-4 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-2">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">AI Provider</div>
                <div class="text-sm font-semibold text-slate-900 mt-0.5">{{ $ai['name'] }}</div>
            </div>
            @if ($ai['status'] === 'connected')
                <span class="badge-ok">Terhubung</span>
            @elseif ($ai['configured'])
                <span class="badge-warn">Belum Diuji</span>
            @else
                <span class="badge-muted">Belum Dikonfigurasi</span>
            @endif
        </div>
        <div class="text-xs text-slate-600 space-y-1">
            <div>Model default: <span class="font-medium text-slate-800">{{ $ai['default_model'] ?? 'Belum dipilih' }}</span></div>
            <div>Fallback: <span class="font-medium text-slate-800">{{ ($ai['fallback_enabled'] ?? false) ? 'Aktif' : 'Nonaktif' }}</span> · OpenRouter: {{ $ai['openrouter_status'] ?? '-' }} · Gemini: {{ $ai['gemini_status'] ?? '-' }}</div>
            <div>Pengujian terakhir: {{ $ai['last_tested_at'] ? $ai['last_tested_at']->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah diuji' }}</div>
        </div>
        <div>
            <a href="{{ route('settings.ai.index') }}" class="btn-secondary w-full text-xs">Atur AI Provider</a>
        </div>
    </div>

    <!-- TTS Provider -->
    <div class="panel p-4 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-2">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">TTS Bahasa Indonesia</div>
                <div class="text-sm font-semibold text-slate-900 mt-0.5">{{ $tts['name'] }}</div>
            </div>
            @if ($tts['status'] === 'connected')
                <span class="badge-ok">Terhubung</span>
            @elseif ($tts['configured'])
                <span class="badge-warn">Belum Diuji</span>
            @else
                <span class="badge-muted">Belum Dikonfigurasi</span>
            @endif
        </div>
        <div class="text-xs text-slate-600 space-y-1">
            <div>Suara aktif: <span class="font-medium text-slate-800">{{ $tts['voice_id'] ?? 'Belum dipilih' }}</span> ({{ $tts['language_code'] }})</div>
            <div>Pengujian terakhir: {{ $tts['last_tested_at'] ? $tts['last_tested_at']->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah diuji' }}</div>
        </div>
        <div>
            <a href="{{ route('settings.tts.index') }}" class="btn-secondary w-full text-xs">Atur Google Cloud TTS</a>
        </div>
    </div>

    <!-- Queue & Scheduler -->
    <div class="panel p-4 flex flex-col justify-between gap-3">
        <div class="flex items-start justify-between gap-2">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sistem Antrean &amp; Scheduler</div>
                <div class="text-sm font-semibold text-slate-900 mt-0.5">Driver: {{ strtoupper($queue['driver']) }} (PostgreSQL)</div>
            </div>
            @if ($queue['failed_jobs'] > 0)
                <span class="badge-err">{{ $queue['failed_jobs'] }} Gagal</span>
            @else
                <span class="badge-ok">Siap</span>
            @endif
        </div>
        <div class="text-xs text-slate-600 space-y-1">
            <div>Antrean tertunda: <span class="font-medium text-slate-800">{{ $queue['pending_jobs'] }}</span> · Gagal: <span class="font-medium text-slate-800">{{ $queue['failed_jobs'] }}</span></div>
            <div>Mode: <span class="font-medium text-slate-800">{{ $operationalMode === 'semi_automatic' ? 'Semi-Automatic' : 'Manual' }}</span> · {{ $queue['timezone'] }}</div>
            <div>Heartbeat scheduler: {{ $heartbeat['checked_at_wib'] ?? 'Belum ada catatan' }}</div>
        </div>
        <div>
            <a href="{{ route('settings.general.index') }}" class="btn-secondary w-full text-xs">Kelola Queue &amp; Umum</a>
        </div>
    </div>
</section>

<!-- Pipeline Readiness & Activity Log -->
<section class="grid grid-cols-1 lg:grid-cols-5 gap-4">
    <div class="panel p-5 lg:col-span-3 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Kesiapan Alur Produksi Plant Coaction</h2>
            <span class="text-xs text-slate-500">Target: 1 Video/Hari</span>
        </div>
        <div class="divide-y divide-slate-100 text-sm">
            <div class="py-2.5 flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-slate-800">1. Koneksi OpenRouter &amp; Pemilihan Model</div>
                    <div class="text-xs text-slate-500">Fondasi analisis produk, strategi konten, dan penulisan naskah.</div>
                </div>
                <span class="{{ $ai['status'] === 'connected' ? 'badge-ok' : 'badge-warn' }}">
                    {{ $ai['status'] === 'connected' ? 'Siap' : 'Perlu API Key' }}
                </span>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-slate-800">2. Google Cloud Text-to-Speech (id-ID)</div>
                    <div class="text-xs text-slate-500">Dubbing Bahasa Indonesia dengan pengaturan kecepatan, pitch, dan volume.</div>
                </div>
                <span class="{{ $tts['status'] === 'connected' ? 'badge-ok' : 'badge-warn' }}">
                    {{ $tts['status'] === 'connected' ? 'Siap' : 'Perlu Kredensial' }}
                </span>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-slate-800">3. Video Rendering (Remotion + FFmpeg)</div>
                    <div class="text-xs text-slate-500">{{ $video['message'] }}</div>
                </div>
                <span class="{{ $video['ready'] ? 'badge-ok' : 'badge-muted' }}">
                    {{ $video['ready'] ? 'Siap' : 'Fase 5' }}
                </span>
            </div>
            <div class="py-2.5 flex items-center justify-between gap-3">
                <div>
                    <div class="font-medium text-slate-800">4. Akun TikTok {{ $tiktok['handle'] }} &amp; Affiliate</div>
                    <div class="text-xs text-slate-500">{{ $tiktok['message'] }}</div>
                </div>
                <span class="badge-muted">Fase 2</span>
            </div>
        </div>
    </div>

    <div class="panel p-5 lg:col-span-2 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Aktivitas Terbaru</h2>
            <a href="{{ route('modules.activity-logs') }}" class="text-xs font-medium text-forest-700 hover:underline">Lihat semua</a>
        </div>
        @if ($overview['recent_activities']->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                Belum ada catatan aktivitas. Mulai dengan mengonfigurasi OpenRouter atau Google Cloud TTS.
            </div>
        @else
            <ul class="divide-y divide-slate-100 text-xs">
                @foreach ($overview['recent_activities'] as $act)
                    <li class="py-2.5 space-y-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-slate-800">{{ $act->module }} · {{ $act->action }}</span>
                            <span class="text-slate-400">{{ $act->created_at?->timezone('Asia/Jakarta')->format('d/m H:i') }}</span>
                        </div>
                        <p class="text-slate-600">{{ $act->description }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endsection
