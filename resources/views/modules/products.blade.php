@extends('layouts.app')

@section('title', 'Riset Produk Affiliate')
@section('header', 'Riset & Kurasi Produk Affiliate TikTok Shop')
@section('subheader', 'Database produk terverifikasi untuk Plant Coaction (tanpa data produk palsu)')

@section('content')
<div class="panel p-6 text-center space-y-2">
    <div class="badge-muted">Dijadwalkan Aktif di Fase 3</div>
    <h2 class="text-sm font-semibold text-slate-900">Belum Ada Produk Affiliate Terverifikasi ({{ $products->count() }})</h2>
    <p class="text-xs text-slate-500 max-w-xl mx-auto">
        Sesuai aturan kerja Fase 1, tabel <code>affiliate_products</code> tidak diisi produk palsu.
        Integrasi riset, scoring, dan kurasi produk kategori Plant Care, Media Tanam, Greenhouse, dan Kolektor akan dijalankan setelah verifikasi TikTok Shop Affiliate pada Fase 2–3.
    </p>
</div>
@endsection
