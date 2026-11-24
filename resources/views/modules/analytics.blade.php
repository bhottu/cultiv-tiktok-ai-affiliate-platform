@extends('layouts.app')

@section('title', 'Analitik Performa')
@section('header', 'Analitik Performa & Konversi Affiliate')
@section('subheader', 'Hanya menampilkan data aktual dari database dan API resmi (tanpa angka fiktif)')

@section('content')
<div class="panel p-6 space-y-3">
    <div class="badge-muted">Data TikTok &amp; Komisi Belum Tersedia</div>
    <h2 class="text-sm font-semibold text-slate-900">Ringkasan Aktual Sistem</h2>
    <p class="text-xs text-slate-600">{{ $summary['note'] }}</p>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 text-xs">
        <div class="rounded-lg border border-slate-200 p-3"><div class="text-slate-500">Post Terpublikasi</div><div class="text-lg font-bold">{{ $summary['published_posts'] }}</div></div>
        <div class="rounded-lg border border-slate-200 p-3"><div class="text-slate-500">Video Selesai</div><div class="text-lg font-bold">{{ $summary['completed_videos'] }}</div></div>
        <div class="rounded-lg border border-slate-200 p-3"><div class="text-slate-500">Views TikTok</div><div class="text-sm font-medium text-slate-400 mt-1">Belum tersedia</div></div>
        <div class="rounded-lg border border-slate-200 p-3"><div class="text-slate-500">Komisi Affiliate</div><div class="text-sm font-medium text-slate-400 mt-1">Belum tersedia</div></div>
    </div>
</div>
@endsection
