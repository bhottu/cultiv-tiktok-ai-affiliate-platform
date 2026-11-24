@extends('layouts.app')

@section('title', 'Kesiapan TikTok Shop Affiliate')
@section('header', 'Kesiapan TikTok Shop Affiliate — Plant Coaction')
@section('subheader', 'Evaluasi jujur status akun, izin API, dan persyaratan resmi tanpa asumsi kelayakan otomatis')

@section('content')
<div class="space-y-4">
    <!-- Main Honest Verification Banner -->
    <div class="panel p-5 border-l-4 border-l-amber-500 space-y-2">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <h2 class="text-sm font-semibold text-slate-900">Status Verifikasi Program TikTok Shop Affiliate</h2>
            <span class="badge-warn">Verifikasi Manual Diperlukan</span>
        </div>
        <p class="text-xs text-slate-700 font-medium">{{ $readiness['affiliate_program_message'] }}</p>
        <p class="text-xs text-slate-500">
            Persyaratan kelayakan (jumlah follower, usia akun, aktivitas, verifikasi identitas &amp; rekening) dapat berbeda untuk setiap akun dan wilayah di Indonesia. Sistem tidak menyimpulkan kelayakan hanya dari jumlah follower.
        </p>
    </div>

    <!-- Compact Readiness Checklist Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="panel p-5 space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">Status Komponen Integrasi</h3>
            <div class="divide-y divide-slate-100 text-xs">
                <div class="py-2.5 flex items-center justify-between gap-2">
                    <span class="text-slate-600">Akun TikTok ({{ $readiness['tiktok_account_handle'] }})</span>
                    <span class="{{ $readiness['tiktok_account_connected'] ? 'badge-ok' : 'badge-muted' }}">
                        {{ $readiness['tiktok_account_connected'] ? 'Terhubung OAuth' : $readiness['tiktok_account_status'] }}
                    </span>
                </div>
                <div class="py-2.5 flex items-center justify-between gap-2">
                    <span class="text-slate-600">Status TikTok Shop Affiliate</span>
                    <span class="badge-warn">Belum Terverifikasi via API</span>
                </div>
                <div class="py-2.5 flex items-center justify-between gap-2">
                    <span class="text-slate-600">Akses Creator Affiliate Center</span>
                    <span class="badge-muted">Periksa Manual</span>
                </div>
                <div class="py-2.5 flex items-center justify-between gap-2">
                    <span class="text-slate-600">Akses TikTok Shop Affiliate Product API</span>
                    <span class="{{ $readiness['affiliate_product_api_ready'] ? 'badge-ok' : 'badge-warn' }}">
                        {{ $readiness['affiliate_product_api_ready'] ? 'Kredensial Tersedia' : 'Menunggu Akses API' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="panel p-5 space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">Persyaratan yang Belum Terpenuhi &amp; Panduan</h3>
            <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5">
                @foreach ($readiness['unmet_requirements'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

            <div class="pt-2 border-t border-slate-100 space-y-1.5">
                <div class="text-xs font-semibold text-slate-700">Tautan Resmi TikTok:</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($readiness['official_links'] as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="btn-secondary !px-3 !py-1.5 text-xs">
                            {{ $link['label'] }} ↗
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Niche Focus -->
    <div class="panel p-5 space-y-2.5">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Prioritas Niche Produk Plant Coaction</h3>
            <a href="{{ route('affiliate.products') }}" class="text-xs font-medium text-forest-700 hover:underline">Buka Riset Produk →</a>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($niches as $niche)
                <span class="badge-muted">{{ $niche }}</span>
            @endforeach
        </div>
    </div>
</div>
@endsection
