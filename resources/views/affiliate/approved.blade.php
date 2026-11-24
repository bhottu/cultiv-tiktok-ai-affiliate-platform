@extends('layouts.app')

@section('title', 'Produk Disetujui')
@section('header', 'Produk Affiliate Disetujui (Siap untuk Produksi Konten)')
@section('subheader', 'Daftar produk yang telah lolos kurasi & persetujuan manual untuk digunakan pada Fase 3+')

@section('content')
@if ($products->isEmpty())
    <div class="panel p-8 text-center space-y-2">
        <span class="badge-muted">0 Produk Disetujui</span>
        <h2 class="text-sm font-semibold text-slate-900">Belum Ada Produk Affiliate yang Disetujui</h2>
        <p class="text-xs text-slate-500 max-w-lg mx-auto">
            Setiap produk harus melewati proses penemuan resmi, analisis AI, dan persetujuan manual di menu <a href="{{ route('affiliate.products') }}" class="text-forest-700 underline">Riset Produk</a> sebelum dapat masuk ke pipeline pembuatan konten.
        </p>
    </div>
@else
    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Produk</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-left">Skor Kurasi</th>
                        <th class="px-4 py-3 text-left">Disetujui Oleh</th>
                        <th class="px-4 py-3 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($products as $p)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $p->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $p->category }}</td>
                            <td class="px-4 py-3">{{ $p->quality_score !== null ? $p->quality_score . '/100' : 'Belum dianalisis' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $p->approver?->name ?? '-' }}
                                <div class="text-[11px] text-slate-400">{{ $p->approved_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $p->approval_notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
