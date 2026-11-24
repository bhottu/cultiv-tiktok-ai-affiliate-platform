@extends('layouts.app')

@section('title', 'Strategi & Pilar Konten')
@section('header', 'Pilar Konten & Bank Ide — Plant Coaction')
@section('subheader', 'Menjaga kredibilitas edukasi tanaman hias & aroid @plant.coaction')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($pillars as $pillar)
        <div class="panel p-4 space-y-1.5">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">{{ $pillar->name }}</h3>
                <span class="badge-ok">{{ $pillar->target_ratio_percent }}%</span>
            </div>
            <p class="text-xs text-slate-600">{{ $pillar->goal }}</p>
            <div class="text-[11px] text-slate-400 pt-1">Ide tersimpan: {{ $pillar->ideas_count }}</div>
        </div>
    @endforeach
</div>

<div class="panel p-6 text-center space-y-2">
    <h3 class="text-sm font-semibold text-slate-900">Bank Ide Konten ({{ $ideas->count() }})</h3>
    <p class="text-xs text-slate-500">Belum ada ide konten yang dibuat. Generator ide otomatis akan aktif pada Fase 4 setelah OpenRouter &amp; kurasi produk siap.</p>
</div>
@endsection
