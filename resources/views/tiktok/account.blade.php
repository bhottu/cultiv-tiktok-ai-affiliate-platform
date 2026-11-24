@extends('layouts.app')

@section('title', 'Akun TikTok')
@section('header', 'Akun TikTok — Koneksi OAuth Resmi')
@section('subheader', 'Kelola koneksi OAuth v2 resmi TikTok, izin scope, token terenkripsi, dan profil @plant.coaction')

@section('content')
@php
    $isConnected = $account && $account->status === 'connected' && ! $account->isTokenExpired();
    $needsReconnect = $account && in_array($account->status, ['token_expired', 'revoked'], true);
    $statusBadge = match($account?->status ?? 'not_connected') {
        'connected' => $account->isTokenExpired() ? ['badge-warn', 'Token Kedaluwarsa'] : ['badge-ok', 'Terhubung'],
        'token_expired' => ['badge-warn', 'Token Kedaluwarsa'],
        'revoked' => ['badge-err', 'Izin Dicabut / Revoked'],
        'disconnected' => ['badge-muted', 'Terputus'],
        default => ['badge-muted', 'Belum Terhubung'],
    };
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
    <!-- A. Account Connection -->
    <div class="lg:col-span-7 panel p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                @if ($account?->avatar_url)
                    <img src="{{ $account->avatar_url }}" alt="Avatar TikTok" class="w-12 h-12 rounded-full object-cover border border-slate-200">
                @else
                    <div class="w-12 h-12 rounded-full bg-forest-100 text-forest-800 flex items-center justify-center font-bold text-sm">PC</div>
                @endif
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-900 truncate">
                        {{ $account?->display_name ?: 'Plant Coaction' }}
                    </div>
                    <div class="text-xs text-slate-500 truncate">
                        {{ $account?->handle ?: '@plant.coaction' }}
                        @if ($account?->external_account_id)
                            · ID: <span class="font-mono">{{ $account->external_account_id }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <span class="{{ $statusBadge[0] }} flex-shrink-0">{{ $statusBadge[1] }}</span>
        </div>

        @if (! $oauthConfigured)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-900 space-y-1">
                <div class="font-semibold">Menunggu Kredensial TikTok Developer</div>
                <p>Isi <code>TIKTOK_CLIENT_KEY</code>, <code>TIKTOK_CLIENT_SECRET</code>, dan <code>TIKTOK_REDIRECT_URI</code> di file <code>.env</code> untuk mengaktifkan tombol Connect TikTok. Lihat panduan di <code>TIKTOK_OAUTH_SETUP.md</code>.</p>
                <div class="text-[11px] text-amber-800 pt-1">Callback URL terdaftar: <code class="font-mono">{{ $redirectUri }}</code> · Mode: <strong>{{ strtoupper($environment) }}</strong></div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs border-y border-slate-100 py-3">
            <div>
                <div class="text-slate-400">Status Token</div>
                <div class="font-medium text-slate-800 mt-0.5">
                    @if (! $account || ! $account->token_expires_at)
                        Belum ada token aktif
                    @elseif ($account->isTokenExpired())
                        Kedaluwarsa ({{ $account->token_expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB)
                    @else
                        Aktif hingga {{ $account->token_expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                    @endif
                </div>
            </div>
            <div>
                <div class="text-slate-400">Koneksi / Sinkronisasi Terakhir</div>
                <div class="font-medium text-slate-800 mt-0.5">
                    {{ $account?->connected_at ? $account->connected_at->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah terhubung' }}
                </div>
            </div>
        </div>

        <!-- Scopes Granted -->
        <div class="space-y-1.5">
            <div class="text-xs font-medium text-slate-700">Izin OAuth (Scopes) yang Diberikan</div>
            @if ($account && ! empty($account->scopes))
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($account->scopes as $scope)
                        <span class="badge-ok font-mono">{{ $scope }}</span>
                    @endforeach
                </div>
            @else
                <div class="text-xs text-slate-400">Belum ada scope aktif. Scope yang diminta saat OAuth: <span class="font-mono">{{ implode(', ', $configuredScopes) }}</span></div>
            @endif
        </div>

        @if ($account?->last_error)
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                <strong>Catatan Error Terakhir:</strong> {{ $account->last_error }}
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-2 pt-1">
            @if (! $isConnected)
                <form method="POST" action="{{ route('tiktok.connect') }}">
                    @csrf
                    <button type="submit" class="btn-primary text-xs">
                        {{ $needsReconnect ? 'Reconnect TikTok' : 'Connect TikTok (OAuth Resmi)' }}
                    </button>
                </form>
            @endif

            @if ($account && in_array($account->status, ['connected', 'token_expired'], true))
                <form method="POST" action="{{ route('tiktok.sync', $account) }}">
                    @csrf
                    <button type="submit" class="btn-secondary text-xs">Sinkronkan Info Akun</button>
                </form>
                <form method="POST" action="{{ route('tiktok.refresh', $account) }}">
                    @csrf
                    <button type="submit" class="btn-secondary text-xs">Refresh Token</button>
                </form>
            @endif

            @if ($account && in_array($account->status, ['connected', 'token_expired', 'revoked'], true))
                <form method="POST" action="{{ route('tiktok.disconnect', $account) }}" onsubmit="return confirm('Putus koneksi akun TikTok dan hapus token terenkripsi lokal?');">
                    @csrf
                    <button type="submit" class="btn-secondary !text-rose-700 !border-rose-200 hover:!bg-rose-50 text-xs">Disconnect TikTok</button>
                </form>
            @endif
        </div>

        <p class="text-[11px] text-slate-400">
            Aplikasi menggunakan OAuth 2.0 resmi TikTok, tidak pernah meminta password TikTok, dan seluruh token disimpan terenkripsi.
        </p>
    </div>

    <!-- B. Account Information & C. Analytics Preview -->
    <div class="lg:col-span-5 space-y-4">
        <div class="panel p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-900">Informasi Akun (Display API)</h2>
            @if ($isConnected && ! empty($account->profile_stats))
                @php $stats = $account->profile_stats; @endphp
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="text-slate-400">Followers</div>
                        <div class="text-lg font-bold text-slate-900">{{ isset($stats['follower_count']) ? number_format((int) $stats['follower_count']) : 'Belum tersedia' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="text-slate-400">Total Likes</div>
                        <div class="text-lg font-bold text-slate-900">{{ isset($stats['likes_count']) ? number_format((int) $stats['likes_count']) : 'Belum tersedia' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="text-slate-400">Video Publik</div>
                        <div class="text-lg font-bold text-slate-900">{{ isset($stats['video_count']) ? number_format((int) $stats['video_count']) : 'Belum tersedia' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="text-slate-400">Following</div>
                        <div class="text-lg font-bold text-slate-900">{{ isset($stats['following_count']) ? number_format((int) $stats['following_count']) : 'Belum tersedia' }}</div>
                    </div>
                </div>
                @if (! empty($stats['bio_description']))
                    <div class="text-xs text-slate-600 border-t border-slate-100 pt-2">
                        <span class="font-medium text-slate-700">Bio:</span> {{ $stats['bio_description'] }}
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-xs text-slate-500">
                    Data profil TikTok belum tersedia melalui integrasi resmi. Hubungkan akun dengan scope <code>user.info.basic</code>, <code>user.info.profile</code>, dan <code>user.info.stats</code>.
                </div>
            @endif
        </div>

        <div class="panel p-5 space-y-2.5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Status Analytics TikTok</h2>
                <a href="{{ route('tiktok.analytics') }}" class="text-xs text-forest-700 font-medium hover:underline">Detail</a>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3.5 text-xs text-slate-600 space-y-1.5">
                <div class="font-medium text-slate-800">Analytics mendalam belum tersedia melalui integrasi resmi.</div>
                <p>TikTok Display API tidak menyediakan demografi audiens, waktu aktif, retention, maupun komisi affiliate. Tidak ada angka dummy yang ditampilkan.</p>
            </div>
        </div>
    </div>
</div>
@endsection
